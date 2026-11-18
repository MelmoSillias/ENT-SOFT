<?php

namespace App\Tests\Integration\Finance;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Entity\Invoice;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineMigrations\Version20261118140000;
use Psr\Log\NullLogger;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InvoicePaymentTermsPersistenceTest extends KernelTestCase
{
    public function testMigrationPreservesLegacyRowsAndIsReversible(): void
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20261118140000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        $table = $schema->createTable('invoices');
        $table->addColumn('id', 'integer');
        $table->addColumn('amount', 'float');
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
        $connection->insert('invoices', ['id' => 1, 'amount' => 123.456]);
        $manager = $connection->createSchemaManager();
        $migration = new Version20261118140000($connection, new NullLogger());
        $before = clone $schema;
        $migration->up($schema);
        self::assertFalse($schema->getTable('invoices')->getColumn('payment_terms')->getNotnull());
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }
        $row = $connection->fetchAssociative('SELECT * FROM invoices');
        self::assertSame(123.456, (float) $row['amount']);
        self::assertNull($row['payment_terms']);
        $connection->executeStatement('UPDATE invoices SET payment_terms = ?', ['["À réception"]']);
        self::assertSame(['À réception'], json_decode($connection->fetchOne('SELECT payment_terms FROM invoices'), true));

        $before = clone $schema;
        $migration->down($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }
        self::assertSame(['id', 'amount'], array_keys($connection->fetchAssociative('SELECT * FROM invoices')));
        self::assertSame(123.456, (float) $connection->fetchOne('SELECT amount FROM invoices'));
        $connection->close();
    }

    public function testDoctrineRoundTripSupportsTermsEmptyListsAndLegacyNull(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        if (!Type::hasType('uuid')) { Type::addType('uuid', UuidType::class); }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $container->set('doctrine.dbal.default_connection', $connection);
        $em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Invoice::class)]);
        $legacy = new Invoice('1', 'ENT1/11-2026', new \DateTimeImmutable('2026-11-18'), 1000, Uuid::v4());
        $invoice = new Invoice('2', 'ENT2/11-2026', new \DateTimeImmutable('2026-11-18'), 2000, Uuid::v4());
        $invoice->setPaymentTerms([' Acompte de 30 % ', '', 'Solde à réception']);
        $em->persist($legacy);
        $em->persist($invoice);
        $em->flush();
        $legacyId = $legacy->getId();
        $id = $invoice->getId();
        $connection->executeStatement('UPDATE invoices SET payment_terms = NULL WHERE number = ?', ['1']);
        $em->clear();
        $legacy = $em->find(Invoice::class, $legacyId);
        self::assertSame([], $legacy->getPaymentTerms());
        self::assertSame([], InvoiceResponseDto::fromEntity($legacy, '1')->toArray()['paymentTerms']);
        self::assertSame(1000.0, $legacy->getAmount());
        $invoice = $em->find(Invoice::class, $id);
        self::assertSame(['Acompte de 30 %', 'Solde à réception'], $invoice->getPaymentTerms());
        self::assertSame($invoice->getPaymentTerms(), InvoiceResponseDto::fromEntity($invoice, '2')->toArray()['paymentTerms']);
        $invoice->setPaymentTerms([]);
        $em->flush();
        $em->clear();
        self::assertSame([], $em->find(Invoice::class, $id)->getPaymentTerms());
        self::assertSame('[]', $connection->fetchOne('SELECT payment_terms FROM invoices WHERE number = ?', ['2']));
    }
}

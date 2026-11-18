<?php

namespace App\Tests\Integration\Finance;

use App\Finance\Domain\Entity\Invoice;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineMigrations\Version20261118120000;

use Psr\Log\NullLogger;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceVatPersistenceTest extends KernelTestCase
{
    public function testMigrationPreservesLegacyAmountsAndIsReversible(): void
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20261118120000.php';
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
        $migration = new Version20261118120000($connection, new NullLogger());
        $before = clone $schema;
        $migration->up($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }
        $row = $connection->fetchAssociative('SELECT * FROM invoices');
        self::assertSame(123.456, (float) $row['amount']);
        self::assertSame(0, (int) $row['vat_enabled']);
        self::assertSame(18.0, (float) $row['vat_rate']);
        self::assertNull($row['subtotal']);

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

    public function testDoctrineRoundTripPersistsVatAndLegacyFallback(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        if (!Type::hasType('uuid')) { Type::addType('uuid', UuidType::class); }
        $container->set('doctrine.dbal.default_connection', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema([$em->getClassMetadata(Invoice::class)]);
        $legacy = new Invoice('1', 'ENT1/11-2026', new \DateTimeImmutable('2026-11-18'), 1000, Uuid::v4());
        $taxed = new Invoice('2', 'ENT2/11-2026', new \DateTimeImmutable('2026-11-18'), 2000, Uuid::v4());
        $taxed->setVat(true, 18);
        $em->persist($legacy);
        $em->persist($taxed);
        $em->flush();
        $legacyId = $legacy->getId();
        $taxedId = $taxed->getId();
        $em->clear();
        $legacy = $em->find(Invoice::class, $legacyId);
        self::assertFalse($legacy->isVatEnabled());
        self::assertSame(1000.0, $legacy->getSubtotal());
        self::assertSame(1000.0, $legacy->getAmount());
        $taxed = $em->find(Invoice::class, $taxedId);
        self::assertTrue($taxed->isVatEnabled());
        self::assertSame(18.0, $taxed->getVatRate());
        self::assertSame(2000.0, $taxed->getSubtotal());
        self::assertSame(360.0, $taxed->getVatAmount());
        self::assertSame(2360.0, $taxed->getAmount());
    }
}

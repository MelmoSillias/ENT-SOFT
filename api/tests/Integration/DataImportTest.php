<?php

namespace App\Tests\Integration;

use App\DataImport\Application\{DataImportService, ImportFormat, ImportRowFactory};
use App\DataImport\Domain\Entity\ImportRecord;
use App\Document\Domain\Entity\Document;
use App\Finance\Domain\Entity\FinancialTransaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;

final class DataImportTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DataImportService $service;
    private string $directory;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        if (!Type::hasType('uuid')) { Type::addType('uuid', UuidType::class); }
        // Isolated in-memory DB: never touches the application's or existing test database.
        $container->set('doctrine.dbal.default_connection', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $this->em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->directory = sys_get_temp_dir().'/ent_import_test_'.bin2hex(random_bytes(6));
        $this->service = new DataImportService($this->em, new ImportFormat(), $container->get(ImportRowFactory::class), $this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/public/uploads/documents/*') ?: [] as $file) { unlink($file); }
        foreach (['/public/uploads/documents', '/public/uploads', '/public', ''] as $suffix) {
            if (is_dir($this->directory.$suffix)) { rmdir($this->directory.$suffix); }
        }
        parent::tearDown();
    }

    private function package(string $rows, array $files = []): array
    {
        return ['csv' => "id_import;date;montant;categorie;emetteur;beneficiaire;pieces_jointes\n".$rows, 'files' => $files];
    }

    public function testPreviewDoesNotWriteAndExecutionAttachesDocumentAndIsIdempotent(): void
    {
        $package = $this->package('DEP1;2026-11-03;12500,50;Transport;Agence;Fournisseur;pieces/recu.pdf', ['pieces/recu.pdf' => "%PDF-1.4\n%%EOF"]);
        $preview = $this->service->run('depenses', $package, false, true);
        self::assertTrue($preview['valid']);
        self::assertSame(1, $preview['ready']);
        self::assertSame(0, $this->em->getRepository(FinancialTransaction::class)->count([]));
        $result = $this->service->run('depenses', $package, true, true);
        self::assertSame(1, $result['created']);
        $transaction = $this->em->find(FinancialTransaction::class, $result['rows'][0]['entityId']);
        self::assertSame(12500.5, $transaction->getAmount());
        $document = $this->em->getRepository(Document::class)->findOneBy([]);
        self::assertSame((string) $transaction->getId(), (string) $document->getOwnerId());
        self::assertFileExists($this->directory.'/public'.$document->getFilePath());
        $retry = $this->service->run('depenses', $package, true, true);
        self::assertSame(1, $retry['skipped']);
        self::assertSame(0, $retry['created']);
        self::assertSame(1, $this->em->getRepository(Document::class)->count([]));
        self::assertSame(1, $this->em->getRepository(ImportRecord::class)->count([]));
    }

    public function testInvalidRowBlocksEntireBatch(): void
    {
        $result = $this->service->run('depenses', $this->package("D1;2026-11-03;10;Autre;A;B;\nD2;2026-02-30;20;Autre;A;B;"), true, true);
        self::assertFalse($result['valid']);
        self::assertSame(1, $result['errors']);
        self::assertSame(0, $this->em->getRepository(FinancialTransaction::class)->count([]));
    }

    public function testChangedIdentifierAndMissingDocumentPermissionAreRejected(): void
    {
        $this->service->run('depenses', $this->package('D1;2026-11-03;10;Autre;A;B;'), true, true);
        $changed = $this->service->run('depenses', $this->package('D1;2026-11-03;99;Autre;A;B;'), true, true);
        self::assertFalse($changed['valid']);
        $denied = $this->service->run('depenses', $this->package('D2;2026-11-03;10;Autre;A;B;pieces/a.txt', ['pieces/a.txt' => 'Justificatif']), true, false);
        self::assertFalse($denied['valid']);
        self::assertSame(1, $this->em->getRepository(FinancialTransaction::class)->count([]));
    }

    public function testImportedReferencesAreResolvedAcrossTypes(): void
    {
        $client = $this->service->run('clients', ['csv' => "id_import;titre\nC1;Client test", 'files' => []], true, true);
        self::assertSame(1, $client['created']);
        $site = $this->service->run('sites', ['csv' => "id_import;code;titre;client_code\nS1;SITE-TEST;Site test;@C1", 'files' => []], true, true);
        self::assertSame(1, $site['created']);
        $prestataire = $this->service->run('prestataires', ['csv' => "id_import;prenom;nom;email;telephone\nP1;A;B;test@example.com;+223 12345678", 'files' => []], true, true);
        self::assertSame(1, $prestataire['created']);
        $prestation = $this->service->run('prestations', ['csv' => "id_import;prestataire_email;description;montant;date;site_code\nPR1;@P1;Intervention;100;2026-11-03;@S1", 'files' => []], true, true);
        self::assertSame(1, $prestation['created']);
        $equipment = $this->service->run('equipements', ['csv' => "id_import;titre;client_code\nE1;Routeur;@C1", 'files' => []], true, true);
        self::assertSame(1, $equipment['created']);
    }

    public function testStorageAndAllRowsAreRolledBackOnDatabaseFailure(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement("CREATE TRIGGER reject_import BEFORE INSERT ON data_import_records BEGIN SELECT RAISE(ABORT, 'forced test failure'); END");
        $package = $this->package("D1;2026-11-03;10;Autre;A;B;pieces/a.txt\nD2;2026-11-03;20;Autre;A;B;", ['pieces/a.txt' => 'Justificatif']);
        try {
            $this->service->run('depenses', $package, true, true);
            self::fail('Expected transaction failure.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            self::assertStringContainsString('annulé', $e->getMessage());
        }
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM financial_transactions'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM documents'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM data_import_records'));
        self::assertSame([], glob($this->directory.'/public/uploads/documents/*'));
    }
}

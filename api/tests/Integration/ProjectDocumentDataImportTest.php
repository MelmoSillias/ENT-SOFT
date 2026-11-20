<?php

namespace App\Tests\Integration;

use App\AccessAudit\Domain\Entity\{Permission, UtilisateurPermission};
use App\Client\Domain\Entity\Client;
use App\DataImport\Application\DataImportService;
use App\DataImport\Domain\Entity\ImportRecord;
use App\IdentityAccess\Application\Service\AccessTokenService;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\Project\Domain\Entity\{Project, ProjectDocument};
use App\Project\Domain\Enum\ProjectDocumentType;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentDataImportTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $em;
    private Utilisateur $user;
    private Client $client;
    private Project $project;
    private Project $other;
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
        $container = self::getContainer();
        if (!\Doctrine\DBAL\Types\Type::hasType('uuid')) {
            \Doctrine\DBAL\Types\Type::addType('uuid', \Symfony\Bridge\Doctrine\Types\UuidType::class);
        }
        $container->set('doctrine.dbal.default_connection', DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
        $this->em = $container->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->user = new Utilisateur('Test', 'Import', '12345678', 'test-import-documents', 'unused', 'TEST_NO_DEFAULTS');
        $this->client = new Client('CLI-1', 'Client livraison');
        $this->project = new Project('PRJ-1', 'Projet livraison', $this->client->getId());
        $this->other = new Project('PRJ-2', 'Autre projet', $this->client->getId());
        foreach ([$this->user, $this->client, $this->project, $this->other] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $token = $container->get(AccessTokenService::class)->create($this->user);
        $this->browser->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$token);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) { if (is_file($path)) { unlink($path); } }
        parent::tearDown();
    }

    private function grant(): void
    {
        $permission = new Permission('project.projects.update', 'Modifier les projets', 'project');
        $this->em->persist($permission);
        $this->em->persist(new UtilisateurPermission($this->user->getId(), $permission, true, $this->user->getId()));
        $this->em->flush();
    }

    private function row(string $id = 'BL1', string $client = 'CLI-1'): array
    {
        return [$id, 'BL-42', '2026-11-20', $client, '[{"description":" Routeur ","quantity":2.5},{"description":"Câble","quantity":1,"observation":" Livré "}]', ' Livraison matériel '];
    }

    private function csv(?array $rows = null): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['id_import', 'numero', 'date', 'client_code', 'lignes', 'objet'], ';', '"', '');
        foreach ($rows ?? [$this->row()] as $row) { fputcsv($stream, $row, ';', '"', ''); }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    private function upload(string $action, array $parameters, ?string $csv = null): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ent_bl_import_');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $csv ?? $this->csv());
        $this->browser->request('POST', '/api/data-import/bons_livraison/'.$action, $parameters, ['file' => new UploadedFile($path, 'livraisons.csv', 'text/csv', null, true)]);
        return json_decode($this->browser->getResponse()->getContent(), true) ?? [];
    }

    public function testFormatsTemplateAndPermissions(): void
    {
        $this->browser->request('GET', '/api/data-import/formats');
        self::assertResponseIsSuccessful();
        self::assertNotContains('bons_livraison', array_column(json_decode($this->browser->getResponse()->getContent(), true)['items'], 'type'));
        $this->browser->request('GET', '/api/data-import/bons_livraison/template');
        self::assertResponseStatusCodeSame(403);
        foreach (['preview', 'execute'] as $action) {
            $this->upload($action, ['projectId' => (string) $this->project->getId()]);
            self::assertResponseStatusCodeSame(403);
        }
        $this->grant();
        $this->browser->request('GET', '/api/data-import/formats');
        self::assertContains('bons_livraison', array_column(json_decode($this->browser->getResponse()->getContent(), true)['items'], 'type'));
        $this->browser->request('GET', '/api/data-import/bons_livraison/template');
        self::assertResponseIsSuccessful();
        self::assertSame("\xEF\xBB\xBFid_import;numero;date;client_code;lignes;objet\r\n", $this->browser->getResponse()->getContent());
    }

    public function testPreviewCreationAndIdempotenceAreScopedToProject(): void
    {
        $this->grant();
        $context = ['projectId' => (string) $this->project->getId()];
        $preview = $this->upload('preview', $context);
        self::assertResponseIsSuccessful();
        self::assertTrue($preview['valid']);
        self::assertSame(1, $preview['ready']);
        self::assertSame(0, $this->em->getRepository(ProjectDocument::class)->count([]));
        self::assertSame(0, $this->em->getRepository(ImportRecord::class)->count([]));
        $created = $this->upload('execute', $context + ['digest' => $preview['digest']]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $created['created']);
        self::assertSame($preview['rows'][0]['data'], $created['rows'][0]['data']);
        $document = $this->em->find(ProjectDocument::class, $created['rows'][0]['entityId']);
        self::assertSame(ProjectDocumentType::DELIVERY_NOTE, $document->getType());
        self::assertSame((string) $this->project->getId(), (string) $document->getProjectId());
        self::assertSame((string) $this->client->getId(), (string) $document->getClientId());
        self::assertSame('BL-42', $document->getNumber());
        self::assertSame('Livraison matériel', $document->getObject());
        self::assertSame(['RAS', 'Livré'], array_column($document->getLines(), 'observation'));
        self::assertSame(1, $this->upload('execute', $context + ['digest' => $preview['digest']])['skipped']);
        $otherContext = ['projectId' => (string) $this->other->getId()];
        $otherPreview = $this->upload('preview', $otherContext);
        self::assertNotSame($preview['digest'], $otherPreview['digest']);
        self::assertSame(1, $this->upload('execute', $otherContext + ['digest' => $otherPreview['digest']])['created']);
        self::assertSame(2, $this->em->getRepository(ProjectDocument::class)->count([]));
        $changed = $this->row();
        $changed[1] = 'BL-CHANGED';
        $changedPreview = $this->upload('preview', $context, $this->csv([$changed]));
        self::assertFalse($changedPreview['valid']);
        $this->upload('execute', $context + ['digest' => $changedPreview['digest']], $this->csv([$changed]));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(2, $this->em->getRepository(ProjectDocument::class)->count([]));
    }

    public function testDigestRejectsChangedProjectFileAndMissingDigest(): void
    {
        $this->grant();
        $context = ['projectId' => (string) $this->project->getId()];
        $preview = $this->upload('preview', $context);
        $this->upload('execute', ['projectId' => (string) $this->other->getId(), 'digest' => $preview['digest']]);
        self::assertResponseStatusCodeSame(409);
        $this->upload('execute', $context + ['digest' => $preview['digest']], $this->csv([$this->row('BL2')]));
        self::assertResponseStatusCodeSame(409);
        $this->upload('execute', $context);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, $this->em->getRepository(ProjectDocument::class)->count([]));
    }

    public function testProjectIsRequiredAndMustBeActiveForBothActionsEvenOnRetry(): void
    {
        $this->grant();
        $context = ['projectId' => (string) $this->project->getId()];
        $preview = $this->upload('preview', $context);
        $this->upload('execute', $context + ['digest' => $preview['digest']]);
        self::assertResponseIsSuccessful();
        $this->em->find(Project::class, $this->project->getId())->disable();
        $this->em->flush();
        foreach (['preview', 'execute'] as $action) {
            foreach ([[[], 400], [['projectId' => 'invalid'], 400], [['projectId' => (string) Uuid::v4()], 404], [$context, 404]] as [$parameters, $status]) {
                $this->upload($action, $parameters + ['digest' => $preview['digest']]);
                self::assertResponseStatusCodeSame($status);
            }
        }
        self::assertSame(1, $this->em->getRepository(ProjectDocument::class)->count([]));
    }

    public function testClientReferencesAndInvalidRowsBlockWholeBatch(): void
    {
        $this->grant();
        $service = self::getContainer()->get(DataImportService::class);
        $clientImport = $service->run('clients', ['csv' => "id_import;titre\nC1;Client importé", 'files' => []], true, false);
        $context = ['projectId' => (string) $this->project->getId()];
        $csv = $this->csv([$this->row('BL1', '@C1')]);
        $preview = $this->upload('preview', $context, $csv);
        self::assertTrue($preview['valid']);
        $created = $this->upload('execute', $context + ['digest' => $preview['digest']], $csv);
        self::assertSame(1, $created['created']);
        self::assertSame($clientImport['rows'][0]['entityId'], (string) $this->em->find(ProjectDocument::class, $created['rows'][0]['entityId'])->getClientId());
        $invalid = $this->row('BL3');
        $invalid[4] = '[{"description":"Routeur","quantity":0}]';
        $csv = $this->csv([$this->row('BL2'), $invalid]);
        $preview = $this->upload('preview', $context, $csv);
        self::assertFalse($preview['valid']);
        self::assertSame(3, $preview['rows'][1]['line']);
        self::assertSame($invalid[4], $preview['rows'][1]['data']['lignes']);
        $executed = $this->upload('execute', $context + ['digest' => $preview['digest']], $csv);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $executed['created']);
        foreach (['UNKNOWN', '@UNKNOWN'] as $reference) {
            self::assertFalse($this->upload('preview', $context, $this->csv([$this->row('BL2', $reference)]))['valid']);
        }
        $this->em->find(Client::class, $this->client->getId())->disable();
        $this->em->find(Client::class, $clientImport['rows'][0]['entityId'])->disable();
        $this->em->flush();
        foreach (['CLI-1', '@C1'] as $reference) {
            self::assertFalse($this->upload('preview', $context, $this->csv([$this->row('BL2', $reference)]))['valid']);
        }
        self::assertSame(1, $this->em->getRepository(ProjectDocument::class)->count([]));
    }

    public function testCsvCannotOverrideProjectAndDuplicateIdsAreRejected(): void
    {
        $this->grant();
        $context = ['projectId' => (string) $this->project->getId()];
        $this->upload('preview', $context, "id_import;numero;date;client_code;lignes;projectId\nBL1;42;2026-11-20;CLI-1;[];".$this->other->getId());
        self::assertResponseStatusCodeSame(400);
        $csv = $this->csv([$this->row(), $this->row()]);
        $preview = $this->upload('preview', $context, $csv);
        self::assertFalse($preview['valid']);
        $this->upload('execute', $context + ['digest' => $preview['digest']], $csv);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(ProjectDocument::class)->count([]));
    }

    public function testDatabaseFailureRollsBackPreviouslyFlushedDocumentsAndImportRecords(): void
    {
        $this->grant();
        $context = ['projectId' => (string) $this->project->getId()];
        $csv = $this->csv([$this->row(), $this->row('BL2')]);
        $preview = $this->upload('preview', $context, $csv);
        $connection = $this->em->getConnection();
        $connection->executeStatement("CREATE TRIGGER reject_second_document BEFORE INSERT ON project_documents WHEN (SELECT COUNT(*) FROM project_documents) = 1 BEGIN SELECT RAISE(ABORT, 'forced test failure'); END");
        $this->upload('execute', $context + ['digest' => $preview['digest']], $csv);
        self::assertResponseStatusCodeSame(500);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM project_documents'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM data_import_records'));
    }
}

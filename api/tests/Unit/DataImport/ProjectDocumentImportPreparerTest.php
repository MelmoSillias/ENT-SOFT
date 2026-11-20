<?php

namespace App\Tests\Unit\DataImport;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\DataImport\Application\{DataImportService, ImportRowFactory, ProjectDocumentImportPreparer};
use App\Project\Application\Service\ProjectDocumentService;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectDocumentType;
use App\Project\Domain\Repository\{ProjectDocumentRepositoryInterface, ProjectRepositoryInterface};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentImportPreparerTest extends TestCase
{
    private function preparer(bool $withProject = true): array
    {
        $project = new Project('PRJ-1', 'Projet', Uuid::v4());
        $projects = $this->createMock(ProjectRepositoryInterface::class);
        $projects->expects($withProject ? self::atLeastOnce() : self::never())->method('findById')->willReturn($project);
        $documents = $this->createMock(ProjectDocumentRepositoryInterface::class);
        $documents->expects(self::never())->method('save');
        $service = new ProjectDocumentService($documents, $projects, $this->createMock(ClientRepositoryInterface::class));
        return [new ProjectDocumentImportPreparer($service), $withProject ? (string) $project->getId() : null];
    }

    private function row(): array
    {
        return ['numero' => ' BL-42 ', 'date' => '2026-11-20', 'lignes' => '[{"description":" Routeur ","quantity":2.5},{"description":"Câble","quantity":1,"observation":" Vérifié "}]', 'objet' => ' Livraison '];
    }

    public static function projectContexts(): iterable
    {
        yield 'avec projet' => [true];
        yield 'sans projet' => [false];
    }

    #[DataProvider('projectContexts')]
    public function testDetachedDocumentUsesDomainRulesWithoutSaving(bool $withProject): void
    {
        [$preparer, $projectId] = $this->preparer($withProject);
        $document = $preparer->prepare($this->row(), (string) Uuid::v4(), $projectId);
        self::assertSame(ProjectDocumentType::DELIVERY_NOTE, $document->getType());
        self::assertSame($projectId, $document->getProjectId()?->toRfc4122());
        self::assertSame('BL-42', $document->getNumber());
        self::assertSame('Livraison', $document->getObject());
        self::assertSame(['RAS', 'Vérifié'], array_column($document->getLines(), 'observation'));
        self::assertSame(2.5, $document->getLines()[0]['quantity']);
    }

    private static function invalidFields(): iterable
    {
        foreach (['{', '{}', '[]', '[[]]', '[null]', '[{"description":"X","quantity":"2"}]', '[{"description":"X","quantity":0}]', '[{"description":"X","quantity":-1}]', '[{"description":" ","quantity":1}]', '[{"description":"X","quantity":1,"observation":[]}]', '[{"description":"X","quantity":1,"projectId":"other"}]', '[{"description":"X","quantity":1e400}]'] as $lines) {
            yield ['lignes', $lines];
        }
        yield ['numero', ' '];
        yield ['numero', str_repeat('x', 256)];
        yield ['objet', str_repeat('x', 501)];
        yield ['date', '2026-02-30'];
        yield ['date', '2026-1-01'];
    }

    public static function invalidRows(): iterable
    {
        foreach (self::invalidFields() as [$field, $value]) {
            foreach ([true, false] as $withProject) {
                yield [$field, $value, $withProject];
            }
        }
    }

    #[DataProvider('invalidRows')]
    public function testInvalidDataIsRejectedBeforePersistence(string $field, string $value, bool $withProject): void
    {
        [$preparer, $projectId] = $this->preparer($withProject);
        $this->expectException(\InvalidArgumentException::class);
        $preparer->prepare(array_replace($this->row(), [$field => $value]), (string) Uuid::v4(), $projectId);
    }

    public function testImportKeyAndDigestAreScopedWithoutChangingExistingTypes(): void
    {
        $first = (string) Uuid::v4();
        $second = (string) Uuid::v4();
        self::assertNotSame(ImportRowFactory::key('bons_livraison', 'BL1', $first), ImportRowFactory::key('bons_livraison', 'BL1', $second));
        self::assertNotSame(ImportRowFactory::key('bons_livraison', 'BL1', $first), ImportRowFactory::key('other', 'BL1', $first));
        self::assertNotSame(ImportRowFactory::key('bons_livraison', 'BL1', $first), ImportRowFactory::key('bons_livraison', 'BL1', null));
        self::assertNotSame(ImportRowFactory::key('bons_livraison', 'BL1', null), ImportRowFactory::key('other', 'BL1', null));
        self::assertSame(hash('sha256', 'clients:C1'), ImportRowFactory::key('clients', 'C1'));
        self::assertSame('fileHash', DataImportService::digest('clients', 'fileHash', null));
        self::assertNotSame(DataImportService::digest('bons_livraison', 'fileHash', $first), DataImportService::digest('bons_livraison', 'fileHash', null));
        self::assertNotSame(DataImportService::digest('bons_livraison', 'fileHash', null), DataImportService::digest('bons_livraison', 'changedHash', null));
        self::assertNotSame(DataImportService::digest('bons_livraison', 'fileHash', $first), DataImportService::digest('bons_livraison', 'fileHash', $second));
        self::assertNotSame(DataImportService::digest('bons_livraison', 'fileHash', $first), DataImportService::digest('bons_livraison', 'changedHash', $first));
    }
}

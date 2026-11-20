<?php

namespace App\Tests\Unit\Project;

use App\Project\Domain\Entity\ProjectDocument;
use App\Project\Domain\Enum\ProjectDocumentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentTest extends TestCase
{
    private function document(string $number, array $lines): ProjectDocument
    {
        return new ProjectDocument(Uuid::v4(), ProjectDocumentType::DELIVERY_NOTE, $number, new \DateTimeImmutable('2026-11-20'), Uuid::v4(), $lines);
    }

    public function testManualNumberAndDefaultObservationsAreNormalized(): void
    {
        $document = $this->document(' BC-42 ', [
            ['description' => ' Routeur ', 'quantity' => 2],
            ['description' => 'Câble', 'quantity' => 1.25, 'observation' => ' '],
            ['description' => 'Switch', 'quantity' => 1, 'observation' => ' Vérifié '],
        ]);
        self::assertSame('BC-42', $document->getNumber());
        self::assertSame(['RAS', 'RAS', 'Vérifié'], array_column($document->getLines(), 'observation'));
        self::assertSame(1.25, $document->getLines()[1]['quantity']);
    }

    public function testProjectIsNullableAndCanBeAssignedOrCleared(): void
    {
        $document = new ProjectDocument(null, ProjectDocumentType::DELIVERY_NOTE, '42', new \DateTimeImmutable('2026-11-20'), Uuid::v4(), [['description' => 'Routeur', 'quantity' => 1]]);
        self::assertNull($document->getProjectId());
        $projectId = Uuid::v4();
        $document->assignProject($projectId);
        self::assertTrue($document->getProjectId()->equals($projectId));
        $document->assignProject(null);
        self::assertNull($document->getProjectId());
    }

    public function testOptionalObjectIsNormalizedAndClearedOnReplacement(): void
    {
        $document = $this->document('42', [['description' => 'Routeur', 'quantity' => 1]]);
        self::assertNull($document->getObject());
        $document->replace($document->getType(), $document->getNumber(), $document->getDate(), $document->getClientId(), $document->getLines(), ' Livraison du site ');
        self::assertSame('Livraison du site', $document->getObject());
        try {
            $document->replace($document->getType(), '43', $document->getDate(), $document->getClientId(), $document->getLines(), str_repeat('x', 501));
            self::fail('Expected validation failure');
        } catch (\InvalidArgumentException) {
            self::assertSame('Livraison du site', $document->getObject());
            self::assertSame('42', $document->getNumber());
        }
        $document->replace($document->getType(), $document->getNumber(), $document->getDate(), $document->getClientId(), $document->getLines(), ' ');
        self::assertNull($document->getObject());
    }

    public static function invalidValues(): iterable
    {
        $line = ['description' => 'Routeur', 'quantity' => 1];
        yield 'blank number' => [' ', [$line]];
        yield 'long number' => [str_repeat('x', 256), [$line]];
        yield 'empty lines' => ['42', []];
        yield 'associative lines' => ['42', ['item' => $line]];
        yield 'not an object' => ['42', [null]];
        yield 'blank description' => ['42', [['description' => ' ', 'quantity' => 1]]];
        foreach ([null, '2', true, [], 0, -1, INF, NAN] as $quantity) {
            yield ['42', [['description' => 'Routeur', 'quantity' => $quantity]]];
        }
        yield 'invalid observation' => ['42', [$line + ['observation' => []]]];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(string $number, array $lines): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->document($number, $lines);
    }

    public function testInvalidReplacementDoesNotMutateTheDocument(): void
    {
        $document = $this->document('42', [['description' => 'Routeur', 'quantity' => 2]]);
        try {
            $document->replace(ProjectDocumentType::DELIVERY_NOTE, '43', new \DateTimeImmutable(), Uuid::v4(), []);
            self::fail('Expected validation failure');
        } catch (\InvalidArgumentException) {
            self::assertSame('42', $document->getNumber());
            self::assertSame(2.0, $document->getLines()[0]['quantity']);
        }
    }
}

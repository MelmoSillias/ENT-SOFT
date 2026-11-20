<?php

namespace App\DataImport\Application;

use App\Project\Application\Service\ProjectDocumentService;
use App\Project\Domain\Entity\ProjectDocument;
use App\Project\Domain\Enum\ProjectDocumentType;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentImportPreparer
{
    public function __construct(private readonly ProjectDocumentService $documents) {}

    public function projectId(?string $projectId): ?string
    {
        if ($projectId === null) {
            return null;
        }
        return $this->documents->project($projectId)->getId()->toRfc4122();
    }

    public function prepare(array $row, ?string $clientId, ?string $projectId): ProjectDocument
    {
        $projectId = $this->projectId($projectId);
        if ($clientId === null) {
            throw new \InvalidArgumentException('client_code : un client existant et actif est requis.');
        }
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $row['date'])
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $row['date']) : false;
        if (!$date || $date->format('Y-m-d') !== $row['date']) {
            throw new \InvalidArgumentException('Date requise au format YYYY-MM-DD.');
        }
        try {
            $lines = json_decode($row['lignes'], false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('lignes : JSON invalide.', previous: $e);
        }
        if (!is_array($lines) || !array_is_list($lines) || $lines === []) {
            throw new \InvalidArgumentException('lignes : une liste JSON non vide est requise.');
        }
        foreach ($lines as $index => $line) {
            if (!$line instanceof \stdClass || array_diff(array_keys(get_object_vars($line)), ['description', 'quantity', 'observation'])) {
                throw new \InvalidArgumentException('lignes['.$index.'] : objet attendu avec description, quantity et éventuellement observation.');
            }
            $lines[$index] = get_object_vars($line);
        }
        // Detached entity: reuse domain validation without persistence during preview.
        return new ProjectDocument($projectId === null ? null : Uuid::fromString($projectId), ProjectDocumentType::DELIVERY_NOTE,
            $row['numero'], $date, Uuid::fromString($clientId), $lines, ($row['objet'] ?? '') !== '' ? $row['objet'] : null);
    }

    public function create(ProjectDocument $document): array
    {
        $data = [
            'type' => $document->getType()->value,
            'number' => $document->getNumber(),
            'date' => $document->getDate()->format('Y-m-d'),
            'clientId' => $document->getClientId()->toRfc4122(),
            'lines' => $document->getLines(),
            'object' => $document->getObject(),
        ];
        $projectId = $document->getProjectId();
        return $projectId === null
            ? $this->documents->saveGlobal($data)
            : $this->documents->save($projectId->toRfc4122(), $data);
    }
}

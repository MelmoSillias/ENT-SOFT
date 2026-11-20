<?php

namespace App\Project\Application\Service;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectDocument;
use App\Project\Domain\Enum\ProjectDocumentType;
use App\Project\Domain\Repository\ProjectDocumentRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentService
{
    public function __construct(
        private readonly ProjectDocumentRepositoryInterface $documents,
        private readonly ProjectRepositoryInterface $projects,
        private readonly ClientRepositoryInterface $clients,
    ) {
    }

    public function project(string $projectId): Project
    {
        $project = $this->projects->findById($this->uuid($projectId, 'projectId'));
        if ($project === null || !$project->isEnabled()) {
            throw new NotFoundHttpException('Projet introuvable.');
        }

        return $project;
    }

    public function get(string $projectId, string $id): ProjectDocument
    {
        $project = $this->project($projectId);
        $document = $this->documents->findById($this->uuid($id, 'id'));
        if ($document === null || !$document->getProjectId()->equals($project->getId())) {
            throw new NotFoundHttpException('Document de projet introuvable.');
        }

        return $document;
    }

    public function list(string $projectId, ?string $type = null): array
    {
        $project = $this->project($projectId);
        $filter = $type === null ? null : $this->type($type);
        $items = $this->documents->findByProjectId($project->getId());

        return array_values(array_map($this->toArray(...), array_filter($items, static fn (ProjectDocument $item) => $filter === null || $item->getType() === $filter)));
    }

    public function save(string $projectId, array $data, ?string $id = null): array
    {
        $project = $this->project($projectId);
        $document = $id === null ? null : $this->get($projectId, $id);
        $type = $this->type($data['type'] ?? null);
        $number = $data['number'] ?? null;
        if (!is_string($number)) {
            throw new \InvalidArgumentException('Le numéro manuel est requis.');
        }
        $clientId = $this->uuid($data['clientId'] ?? null, 'clientId');
        $client = $this->clients->findById($clientId);
        if ($client === null || !$client->isEnabled()) {
            throw new \InvalidArgumentException('Client introuvable ou désactivé.');
        }
        $rawDate = $data['date'] ?? null;
        $date = is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $rawDate)
                    ? \DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate)
                    : false;
        if ($date === false || $date->format('Y-m-d') !== $rawDate) {
            throw new \InvalidArgumentException('Date requise au format YYYY-MM-DD.');
        }
        if (!is_array($data['lines'] ?? null)) {
            throw new \InvalidArgumentException('Les lignes sont requises.');
        }
        $object = $data['object'] ?? null;
        if ($object !== null && !is_string($object)) {
            throw new \InvalidArgumentException('L’objet doit être un texte.');
        }
        if ($document === null) {
            $document = new ProjectDocument($project->getId(), $type, $number, $date, $clientId, $data['lines'], $object);
        } else {
            $document->replace($type, $number, $date, $clientId, $data['lines'], $object);
        }
        $this->documents->save($document);

        return $this->toArray($document);
    }

    public function delete(string $projectId, string $id): void
    {
        $this->documents->remove($this->get($projectId, $id));
    }

    public function toArray(ProjectDocument $document): array
    {
        return [
            'id' => $document->getId()->toRfc4122(),
            'type' => $document->getType()->value,
            'number' => $document->getNumber(),
                        'object' => $document->getObject(),
            'date' => $document->getDate()->format('Y-m-d'),
            'clientId' => $document->getClientId()->toRfc4122(),
            'clientTitle' => $this->clients->findById($document->getClientId())?->getTitle() ?? '—',
            'lines' => $document->getLines(),
        ];
    }

    private function uuid(mixed $value, string $field): Uuid
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new \InvalidArgumentException($field.' : UUID requis.');
        }

        return Uuid::fromString($value);
    }

    private function type(mixed $value): ProjectDocumentType
    {
        $type = is_string($value) ? ProjectDocumentType::tryFrom($value) : null;
        if ($type === null) {
            throw new \InvalidArgumentException('Type de document non supporté.');
        }

        return $type;
    }
}

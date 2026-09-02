<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\Project;

final readonly class ProjectResponseDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $title,
        public ?string $object,
        public ?string $dateDebut,
        public ?string $dateFin,
        public string $status,
        public float $budget,
        public string $clientId,
        public array $sitesInformations,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Project $project): self
    {
        return new self(
            id: (string) $project->getId(),
            code: $project->getCode(),
            title: $project->getTitle(),
            object: $project->getObject(),
            dateDebut: $project->getDateDebut()?->format('Y-m-d'),
            dateFin: $project->getDateFin()?->format('Y-m-d'),
            status: $project->getStatus()->value,
            budget: $project->getBudget(),
            clientId: (string) $project->getClientId(),
            sitesInformations: $project->getSitesInformations(),
            isEnabled: $project->isEnabled(),
            createdAt: $project->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $project->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'object' => $this->object,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin,
            'status' => $this->status,
            'budget' => $this->budget,
            'clientId' => $this->clientId,
            'sitesInformations' => $this->sitesInformations,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

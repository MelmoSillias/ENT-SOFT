<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\Project;

final readonly class ProjectDetailResponseDto
{
    /** @param list<array<string, mixed>> $sites */
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
        public ?string $clientTitle,
        public array $sitesInformations,
        public array $lots,
        public array $sites,
        public array $events,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
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
            'clientTitle' => $this->clientTitle,
            'sitesInformations' => $this->sitesInformations,
            'lots' => $this->lots,
            'sites' => $this->sites,
            'events' => $this->events,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

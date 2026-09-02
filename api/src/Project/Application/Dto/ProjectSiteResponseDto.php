<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\ProjectSite;

final readonly class ProjectSiteResponseDto
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $siteId,
        public string $status,
        public string $dateAdded,
        public array $informationsValues,
        public array $employeeIds,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(ProjectSite $ps): self
    {
        return new self(
            id: (string) $ps->getId(),
            projectId: (string) $ps->getProjectId(),
            siteId: (string) $ps->getSiteId(),
            status: $ps->getStatus()->value,
            dateAdded: $ps->getDateAdded()->format(\DateTimeInterface::ATOM),
            informationsValues: $ps->getInformationsValues(),
            employeeIds: $ps->getEmployeeIds(),
            createdAt: $ps->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $ps->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'status' => $this->status,
            'dateAdded' => $this->dateAdded,
            'informationsValues' => $this->informationsValues,
            'employeeIds' => $this->employeeIds,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

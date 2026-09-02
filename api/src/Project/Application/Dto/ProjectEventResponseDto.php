<?php

namespace App\Project\Application\Dto;

use App\Project\Domain\Entity\ProjectEvent;

final readonly class ProjectEventResponseDto
{
    public function __construct(
        public string $id,
        public string $projectId,
        public string $date,
        public string $title,
    ) {
    }

    public static function fromEntity(ProjectEvent $event): self
    {
        return new self(
            id: (string) $event->getId(),
            projectId: (string) $event->getProjectId(),
            date: $event->getDate()->format('Y-m-d'),
            title: $event->getTitle(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'projectId' => $this->projectId,
            'date' => $this->date,
            'title' => $this->title,
        ];
    }
}

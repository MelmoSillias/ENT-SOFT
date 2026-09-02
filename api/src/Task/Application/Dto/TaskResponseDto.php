<?php

namespace App\Task\Application\Dto;

use App\Task\Domain\Entity\Task;

final readonly class TaskResponseDto
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $dateCreation,
        public ?string $dateDue,
        public string $status,
        public string $siteId,
        public ?string $employeeId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: (string) $task->getId(),
            title: $task->getTitle(),
            description: $task->getDescription(),
            dateCreation: $task->getDateCreation()->format(\DateTimeInterface::ATOM),
            dateDue: $task->getDateDue()?->format('Y-m-d'),
            status: $task->getStatus()->value,
            siteId: (string) $task->getSiteId(),
            employeeId: $task->getEmployeeId()?->toRfc4122(),
            isEnabled: $task->isEnabled(),
            createdAt: $task->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $task->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'dateCreation' => $this->dateCreation,
            'dateDue' => $this->dateDue,
            'status' => $this->status,
            'siteId' => $this->siteId,
            'employeeId' => $this->employeeId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

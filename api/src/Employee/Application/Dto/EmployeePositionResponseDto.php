<?php

namespace App\Employee\Application\Dto;

use App\Employee\Domain\Entity\Employee;
use App\Employee\Domain\Entity\EmployeePosition;

final readonly class EmployeePositionResponseDto
{
    public function __construct(
        public string $id,
        public string $employeeId,
        public ?string $employeeName,
        public float $latitude,
        public float $longitude,
        public ?float $accuracy,
        public string $recordedAt,
        public string $createdAt,
    ) {
    }

    public static function fromEntity(EmployeePosition $position, ?Employee $employee = null): self
    {
        return new self(
            id: (string) $position->getId(),
            employeeId: $position->getEmployeeId()->toRfc4122(),
            employeeName: $employee?->getFullName(),
            latitude: $position->getLatitude(),
            longitude: $position->getLongitude(),
            accuracy: $position->getAccuracy(),
            recordedAt: $position->getRecordedAt()->format(\DateTimeInterface::ATOM),
            createdAt: $position->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'employeeId' => $this->employeeId,
            'employeeName' => $this->employeeName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'recordedAt' => $this->recordedAt,
            'createdAt' => $this->createdAt,
        ];
    }
}

<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\StockMovement;

final readonly class StockMovementResponseDto
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(
        public string $id,
        public string $date,
        public float $quantity,
        public string $unit,
        public string $direction,
        public ?string $clientId,
        public ?string $projectId,
        public ?string $siteId,
        public array $lines,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /** @param list<array<string, mixed>> $lines */
    public static function fromEntity(StockMovement $movement, array $lines): self
    {
        return new self(
            id: (string) $movement->getId(),
            date: $movement->getDate()->format('Y-m-d'),
            quantity: $movement->getQuantity(),
            unit: $movement->getUnit(),
            direction: $movement->getDirection()->value,
            clientId: $movement->getClientId()?->toRfc4122(),
            projectId: $movement->getProjectId()?->toRfc4122(),
            siteId: $movement->getSiteId()?->toRfc4122(),
            lines: $lines,
            isEnabled: $movement->isEnabled(),
            createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'direction' => $this->direction,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'lines' => $this->lines,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

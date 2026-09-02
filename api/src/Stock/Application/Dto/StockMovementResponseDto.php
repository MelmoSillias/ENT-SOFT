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
        public ?string $clientId,
        public ?string $projectId,
        public ?string $siteId,
        public array $lines,
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
            'date' => $this->date,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
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

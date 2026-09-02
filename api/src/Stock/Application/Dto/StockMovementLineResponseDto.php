<?php

namespace App\Stock\Application\Dto;

use App\Stock\Domain\Entity\StockMovementLine;

final readonly class StockMovementLineResponseDto
{
    public function __construct(
        public string $id,
        public string $equipmentId,
        public float $quantity,
    ) {
    }

    public static function fromEntity(StockMovementLine $line): self
    {
        return new self(
            id: (string) $line->getId(),
            equipmentId: (string) $line->getEquipmentId(),
            quantity: $line->getQuantity(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'equipmentId' => $this->equipmentId,
            'quantity' => $this->quantity,
        ];
    }
}

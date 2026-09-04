<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\InvoiceLine;

final readonly class InvoiceLineResponseDto
{
    public function __construct(
        public string $id,
        public string $description,
        public string $unit,
        public float $quantity,
        public float $unitPrice,
        public float $amount,
    ) {
    }

    public static function fromEntity(InvoiceLine $line): self
    {
        return new self(
            id: (string) $line->getId(),
            description: $line->getDescription(),
            unit: $line->getUnit(),
            quantity: $line->getQuantity(),
            unitPrice: $line->getUnitPrice(),
            amount: $line->getAmount(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'amount' => $this->amount,
        ];
    }
}

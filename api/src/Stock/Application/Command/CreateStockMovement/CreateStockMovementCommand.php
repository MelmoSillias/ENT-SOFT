<?php

namespace App\Stock\Application\Command\CreateStockMovement;

final readonly class CreateStockMovementCommand
{
    /** @param list<array{equipmentId: string, quantity: float}> $lines */
    public function __construct(
        public string $date,
        public float $quantity,
        public string $unit,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
        public array $lines = [],
    ) {
    }
}

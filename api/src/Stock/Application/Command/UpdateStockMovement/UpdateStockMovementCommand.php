<?php

namespace App\Stock\Application\Command\UpdateStockMovement;

final readonly class UpdateStockMovementCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $quantity = null,
        public ?string $unit = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
    ) {
    }
}

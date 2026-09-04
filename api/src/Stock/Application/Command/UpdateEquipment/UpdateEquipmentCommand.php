<?php

namespace App\Stock\Application\Command\UpdateEquipment;

final readonly class UpdateEquipmentCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $unit = null,
        public ?string $clientId = null,
        public bool $hasDescription = false,
        public bool $hasClientId = false,
    ) {
    }
}

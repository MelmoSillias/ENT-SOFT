<?php

namespace App\Stock\Application\Command\CreateEquipment;

final readonly class CreateEquipmentCommand
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $clientId = null,
    ) {
    }
}

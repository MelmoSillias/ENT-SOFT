<?php

namespace App\Client\Application\Command\UpdateClient;

final readonly class UpdateClientCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $address = null,
        public ?string $postalBox = null,
        public ?string $city = null,
        public bool $hasAddress = false,
        public bool $hasPostalBox = false,
        public bool $hasCity = false,
        public bool $hasDescription = false,
    ) {
    }
}

<?php

namespace App\Client\Application\Command\CreateClient;

final readonly class CreateClientCommand
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $address = null,
        public ?string $postalBox = null,
        public ?string $city = null,
    ) {
    }
}

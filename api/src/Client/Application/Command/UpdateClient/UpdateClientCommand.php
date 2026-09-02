<?php

namespace App\Client\Application\Command\UpdateClient;

final readonly class UpdateClientCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
    ) {
    }
}

<?php

namespace App\Client\Application\Command\CreateClientContact;

final readonly class CreateClientContactCommand
{
    public function __construct(
        public string $clientId,
        public string $name,
        public string $phone,
    ) {
    }
}

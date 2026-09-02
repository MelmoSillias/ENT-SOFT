<?php

namespace App\Site\Application\Command\CreateSite;

final readonly class CreateSiteCommand
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $clientId = null,
    ) {
    }
}

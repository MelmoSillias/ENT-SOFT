<?php

namespace App\Site\Application\Command\CreateSite;

final readonly class CreateSiteCommand
{
    public function __construct(
        public string $code,
        public string $title,
        public ?string $description = null,
        public ?string $clientId = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }
}

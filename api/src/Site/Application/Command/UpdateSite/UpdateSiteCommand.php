<?php

namespace App\Site\Application\Command\UpdateSite;

final readonly class UpdateSiteCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $clientId = null,
    ) {
    }
}

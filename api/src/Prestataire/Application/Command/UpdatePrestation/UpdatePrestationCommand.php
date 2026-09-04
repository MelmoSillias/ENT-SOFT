<?php

namespace App\Prestataire\Application\Command\UpdatePrestation;

final readonly class UpdatePrestationCommand
{
    public function __construct(
        public string $id,
        public ?string $description = null,
        public ?float $amount = null,
        public ?string $siteId = null,
        public bool $hasSiteId = false,
        public ?string $workStatus = null,
    ) {
    }
}

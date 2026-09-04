<?php

namespace App\Prestataire\Application\Command\CreatePrestation;

final readonly class CreatePrestationCommand
{
    public function __construct(
        public string $prestataireId,
        public string $description,
        public float $amount,
        public ?string $siteId = null,
        public ?string $workStatus = null,
    ) {
    }
}

<?php

namespace App\Prestataire\Application\Command\PayPrestation;

final readonly class PayPrestationCommand
{
    public function __construct(
        public string $id,
        public float $amount,
        public ?string $date = null,
        public ?string $description = null,
    ) {
    }
}

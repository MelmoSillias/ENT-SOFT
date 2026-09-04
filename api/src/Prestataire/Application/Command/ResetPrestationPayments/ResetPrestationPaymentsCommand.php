<?php

namespace App\Prestataire\Application\Command\ResetPrestationPayments;

final readonly class ResetPrestationPaymentsCommand
{
    public function __construct(public string $id) {}
}

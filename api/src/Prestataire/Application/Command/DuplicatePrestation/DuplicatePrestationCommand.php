<?php

namespace App\Prestataire\Application\Command\DuplicatePrestation;

final readonly class DuplicatePrestationCommand
{
    public function __construct(public string $id) {}
}

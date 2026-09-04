<?php

namespace App\Prestataire\Application\Command\DeletePrestation;

final readonly class DeletePrestationCommand
{
    public function __construct(public string $id) {}
}

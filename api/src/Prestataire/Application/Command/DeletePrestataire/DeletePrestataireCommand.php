<?php

namespace App\Prestataire\Application\Command\DeletePrestataire;

final readonly class DeletePrestataireCommand
{
    public function __construct(public string $id) {}
}

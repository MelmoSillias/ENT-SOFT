<?php

namespace App\Prestataire\Application\Command\CreatePrestataire;

final readonly class CreatePrestataireCommand
{
    public function __construct(
        public string $prenom,
        public string $nom,
        public string $email,
        public string $phone,
        public ?string $address = null,
    ) {
    }
}

<?php

namespace App\Prestataire\Application\Command\UpdatePrestataire;

final readonly class UpdatePrestataireCommand
{
    public function __construct(
        public string $id,
        public ?string $prenom = null,
        public ?string $nom = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public bool $hasAddress = false,
    ) {
    }
}

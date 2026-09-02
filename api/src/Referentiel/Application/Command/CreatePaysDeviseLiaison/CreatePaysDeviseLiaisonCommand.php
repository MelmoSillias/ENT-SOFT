<?php

namespace App\Referentiel\Application\Command\CreatePaysDeviseLiaison;

use Symfony\Component\Uid\Uuid;

final readonly class CreatePaysDeviseLiaisonCommand
{
    public function __construct(
        public Uuid $paysId,
        public Uuid $deviseId,
        public string $tauxDefaut,
        public Uuid $utilisateurId,
        public bool $isDefaut = false,
        public ?string $motif = null,
    ) {
    }
}

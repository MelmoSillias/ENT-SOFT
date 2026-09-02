<?php

namespace App\Referentiel\Application\Command\UpdatePaysDeviseLiaison;

use Symfony\Component\Uid\Uuid;

final readonly class UpdatePaysDeviseLiaisonCommand
{
    public function __construct(
        public Uuid $liaisonId,
        public Uuid $utilisateurId,
        public ?Uuid $paysId = null,
        public ?Uuid $deviseId = null,
        public ?string $tauxDefaut = null,
        public ?bool $isDefaut = null,
        public ?string $motif = null,
    ) {
    }
}

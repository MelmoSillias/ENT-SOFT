<?php

namespace App\Configuration\Domain\Event;

use App\SharedKernel\Domain\Event\AbstractDomainEvent;
use Symfony\Component\Uid\Uuid;

final class SettingModifie extends AbstractDomainEvent
{
    public function __construct(
        private readonly string $cle,
        private readonly string $ancienneValeur,
        private readonly string $nouvelleValeur,
        private readonly Uuid $utilisateurId,
        private readonly ?string $motif = null,
    ) {
        parent::__construct();
    }

    public function cle(): string
    {
        return $this->cle;
    }

    public function ancienneValeur(): string
    {
        return $this->ancienneValeur;
    }

    public function nouvelleValeur(): string
    {
        return $this->nouvelleValeur;
    }

    public function utilisateurId(): Uuid
    {
        return $this->utilisateurId;
    }

    public function motif(): ?string
    {
        return $this->motif;
    }
}

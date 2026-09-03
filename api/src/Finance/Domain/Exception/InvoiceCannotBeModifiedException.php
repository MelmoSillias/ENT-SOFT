<?php

namespace App\Finance\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class InvoiceCannotBeModifiedException extends DomainException
{
    public static function hasPayments(): self
    {
        return new self('Cette facture a déjà des paiements. Réinitialisez-la avant de la modifier.');
    }

    public static function cannotDelete(): self
    {
        return new self('Seule une facture brouillon sans paiement peut être supprimée.');
    }
}

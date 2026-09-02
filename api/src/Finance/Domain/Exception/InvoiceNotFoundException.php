<?php

namespace App\Finance\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class InvoiceNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Facture introuvable : %s.', $id));
    }
}

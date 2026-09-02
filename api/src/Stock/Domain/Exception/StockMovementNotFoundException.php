<?php

namespace App\Stock\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class StockMovementNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Mouvement de stock introuvable : %s.', $id));
    }
}

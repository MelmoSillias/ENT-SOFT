<?php

namespace App\Finance\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class FinancialTransactionNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Transaction introuvable : %s.', $id));
    }
}

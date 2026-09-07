<?php

namespace App\Finance\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class SystemGeneratedTransactionException extends DomainException
{
    public static function cannotModify(): self
    {
        return new self('Cette transaction est générée automatiquement et ne peut pas être modifiée manuellement.');
    }

    public static function cannotDelete(): self
    {
        return new self('Cette transaction est générée automatiquement et ne peut pas être supprimée manuellement.');
    }
}

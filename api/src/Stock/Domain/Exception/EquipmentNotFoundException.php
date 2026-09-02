<?php

namespace App\Stock\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class EquipmentNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Équipement introuvable : %s.', $id));
    }
}

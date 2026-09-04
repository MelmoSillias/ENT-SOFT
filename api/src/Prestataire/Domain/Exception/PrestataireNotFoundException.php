<?php

namespace App\Prestataire\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class PrestataireNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Prestataire introuvable : %s.', $id));
    }
}

<?php

namespace App\IdentityAccess\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class UserNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Utilisateur introuvable : %s', $id));
    }
}

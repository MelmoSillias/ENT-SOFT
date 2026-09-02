<?php

namespace App\Client\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class ClientNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Client introuvable : %s.', $id));
    }
}

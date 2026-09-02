<?php

namespace App\Document\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class DocumentNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Document introuvable : %s.', $id));
    }
}

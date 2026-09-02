<?php

namespace App\Site\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class SiteNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Site introuvable : %s.', $id));
    }
}

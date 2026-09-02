<?php

namespace App\Project\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class ProjectNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Projet introuvable : %s.', $id));
    }
}

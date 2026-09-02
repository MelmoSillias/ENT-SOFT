<?php

namespace App\Project\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class ProjectSiteNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Site du projet introuvable : %s.', $id));
    }
}

<?php

namespace App\Task\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class TaskNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Tâche introuvable : %s.', $id));
    }
}

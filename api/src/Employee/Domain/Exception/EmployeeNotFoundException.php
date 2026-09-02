<?php

namespace App\Employee\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class EmployeeNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Employé introuvable : %s.', $id));
    }
}

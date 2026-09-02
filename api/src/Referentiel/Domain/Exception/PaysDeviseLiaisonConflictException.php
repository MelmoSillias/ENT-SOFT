<?php

namespace App\Referentiel\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;

final class PaysDeviseLiaisonConflictException extends DomainException
{
    public function __construct(string $message = 'Liaison déjà existante')
    {
        parent::__construct($message);
    }
}

<?php

namespace App\Referentiel\Domain\Exception;

use App\SharedKernel\Domain\Exception\DomainException;
use Symfony\Component\Uid\Uuid;

final class PaysDeviseLiaisonNotFoundException extends DomainException
{
    public function __construct(Uuid $id)
    {
        parent::__construct(sprintf('Liaison introuvable (%s).', $id));
    }
}

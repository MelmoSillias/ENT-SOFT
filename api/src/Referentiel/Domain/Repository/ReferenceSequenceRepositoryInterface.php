<?php

namespace App\Referentiel\Domain\Repository;

use App\Referentiel\Domain\Enum\ReferenceSequenceType;

interface ReferenceSequenceRepositoryInterface
{
    public function getAndIncrement(ReferenceSequenceType $type): int;
}

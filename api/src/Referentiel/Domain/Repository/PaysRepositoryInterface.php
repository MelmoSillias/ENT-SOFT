<?php

namespace App\Referentiel\Domain\Repository;

use App\Referentiel\Domain\Entity\Pays;
use Symfony\Component\Uid\Uuid;

interface PaysRepositoryInterface
{
    public function findById(Uuid $id): ?Pays;

    public function findByCode(string $code): ?Pays;

    /** @return list<Pays> */
    public function findAll(): array;

    public function save(Pays $pays, bool $flush = true): void;
}

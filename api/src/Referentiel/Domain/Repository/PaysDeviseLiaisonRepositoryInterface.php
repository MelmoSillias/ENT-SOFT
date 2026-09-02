<?php

namespace App\Referentiel\Domain\Repository;

use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use Symfony\Component\Uid\Uuid;

interface PaysDeviseLiaisonRepositoryInterface
{
    public function findById(Uuid $id): ?PaysDeviseLiaison;

    /** @return list<PaysDeviseLiaison> */
    public function findByPaysId(Uuid $paysId): array;

    public function findByPaysAndDevise(Uuid $paysId, Uuid $deviseId): ?PaysDeviseLiaison;

    /** @return list<PaysDeviseLiaison> */
    public function findAll(): array;

    public function clearDefaultExcept(?Uuid $exceptId = null, bool $flush = true): void;

    public function save(PaysDeviseLiaison $liaison, bool $flush = true): void;

    public function remove(PaysDeviseLiaison $liaison, bool $flush = true): void;
}

<?php

namespace App\Referentiel\Domain\Repository;

use App\Referentiel\Domain\Entity\Devise;
use Symfony\Component\Uid\Uuid;

interface DeviseRepositoryInterface
{
    public function findById(Uuid $id): ?Devise;

    public function findByCode(string $code): ?Devise;

    /** @return list<Devise> */
    public function findAll(): array;

    public function save(Devise $devise, bool $flush = true): void;
}

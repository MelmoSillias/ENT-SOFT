<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\Equipment;
use Symfony\Component\Uid\Uuid;

interface EquipmentRepositoryInterface
{
    public function save(Equipment $equipment): void;

    public function findById(Uuid $id): ?Equipment;

    /** @return list<Equipment> */
    public function findAllEnabled(?string $search = null): array;
}

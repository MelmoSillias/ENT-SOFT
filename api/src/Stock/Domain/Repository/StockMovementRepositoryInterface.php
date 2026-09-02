<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\StockMovement;
use Symfony\Component\Uid\Uuid;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    public function findById(Uuid $id): ?StockMovement;

    /** @return list<StockMovement> */
    public function findAllEnabled(): array;
}

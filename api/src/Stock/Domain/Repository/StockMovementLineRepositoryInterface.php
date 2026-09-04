<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\StockMovementLine;
use Symfony\Component\Uid\Uuid;

interface StockMovementLineRepositoryInterface
{
    public function save(StockMovementLine $line): void;

    public function remove(StockMovementLine $line): void;

    /** @return list<StockMovementLine> */
    public function findByMovementId(Uuid $movementId): array;

    /**
     * Net stock quantity per equipment (entrées − sorties).
     *
     * @return array<string, float> equipmentId => quantity
     */
    public function sumNetQuantitiesByEquipment(): array;
}

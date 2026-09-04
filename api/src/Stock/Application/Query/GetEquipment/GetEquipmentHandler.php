<?php

namespace App\Stock\Application\Query\GetEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(GetEquipmentQuery $query): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($query->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($query->id);
        }

        $quantities = $this->lineRepository->sumNetQuantitiesByEquipment();
        $quantity = $quantities[(string) $equipment->getId()] ?? 0.0;

        return EquipmentResponseDto::fromEntity($equipment, $quantity);
    }
}

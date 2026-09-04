<?php

namespace App\Stock\Application\Query\ListEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;

final readonly class ListEquipmentQuery
{
    public function __construct(public ?string $search = null) {}
}

final class ListEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListEquipmentQuery $query): array
    {
        $quantities = $this->lineRepository->sumNetQuantitiesByEquipment();

        return array_map(
            static fn ($e) => EquipmentResponseDto::fromEntity(
                $e,
                $quantities[(string) $e->getId()] ?? 0.0,
            )->toArray(),
            $this->equipmentRepository->findAllEnabled($query->search),
        );
    }
}

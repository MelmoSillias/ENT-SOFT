<?php

namespace App\Stock\Application\Query\ListStockMovements;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;

final class ListStockMovementsHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(?string $from = null, ?string $to = null): array
    {
        $result = [];
        $movements = $this->movementRepository->findAllEnabled(
            $from ? new \DateTimeImmutable($from) : null,
            $to ? new \DateTimeImmutable($to) : null,
        );
        foreach ($movements as $movement) {
            $lines = array_map(
                static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
                $this->lineRepository->findByMovementId($movement->getId()),
            );
            $result[] = StockMovementResponseDto::fromEntity($movement, $lines)->toArray();
        }

        return $result;
    }
}

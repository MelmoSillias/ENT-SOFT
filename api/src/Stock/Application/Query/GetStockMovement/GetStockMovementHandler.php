<?php

namespace App\Stock\Application\Query\GetStockMovement;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Exception\StockMovementNotFoundException;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(GetStockMovementQuery $query): StockMovementResponseDto
    {
        $movement = $this->movementRepository->findById(Uuid::fromString($query->id));
        if (null === $movement || !$movement->isEnabled()) {
            throw StockMovementNotFoundException::withId($query->id);
        }

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return StockMovementResponseDto::fromEntity($movement, $lines);
    }
}

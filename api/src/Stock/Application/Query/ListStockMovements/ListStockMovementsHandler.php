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
    public function handle(): array
    {
        $result = [];
        foreach ($this->movementRepository->findAllEnabled() as $movement) {
            $lines = array_map(
                static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
                $this->lineRepository->findByMovementId($movement->getId()),
            );
            $result[] = (new StockMovementResponseDto(
                id: (string) $movement->getId(),
                date: $movement->getDate()->format('Y-m-d'),
                quantity: $movement->getQuantity(),
                unit: $movement->getUnit(),
                clientId: $movement->getClientId()?->toRfc4122(),
                projectId: $movement->getProjectId()?->toRfc4122(),
                siteId: $movement->getSiteId()?->toRfc4122(),
                lines: $lines,
                isEnabled: $movement->isEnabled(),
                createdAt: $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
                updatedAt: $movement->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            ))->toArray();
        }

        return $result;
    }
}

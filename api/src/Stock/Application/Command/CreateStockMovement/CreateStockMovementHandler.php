<?php

namespace App\Stock\Application\Command\CreateStockMovement;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Entity\StockMovement;
use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Enum\StockMovementDirection;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(CreateStockMovementCommand $command): StockMovementResponseDto
    {
        $unit = FieldValidator::requireNonEmpty($command->unit, 'Unité');
        $movement = new StockMovement(
            date: new \DateTimeImmutable($command->date),
            quantity: $command->quantity,
            unit: $unit,
            direction: StockMovementDirection::from($command->direction),
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
        );
        $this->movementRepository->save($movement);

        $lineDtos = [];
        foreach ($command->lines as $lineData) {
            $line = new StockMovementLine(
                movement: $movement,
                equipmentId: Uuid::fromString($lineData['equipmentId']),
                quantity: (float) $lineData['quantity'],
            );
            $this->lineRepository->save($line);
            $lineDtos[] = StockMovementLineResponseDto::fromEntity($line)->toArray();
        }

        return StockMovementResponseDto::fromEntity($movement, $lineDtos);
    }
}

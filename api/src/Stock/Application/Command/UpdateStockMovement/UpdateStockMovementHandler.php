<?php

namespace App\Stock\Application\Command\UpdateStockMovement;

use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Enum\StockMovementDirection;
use App\Stock\Domain\Exception\StockMovementNotFoundException;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateStockMovementHandler
{
    public function __construct(
        private readonly StockMovementRepositoryInterface $movementRepository,
        private readonly StockMovementLineRepositoryInterface $lineRepository,
    ) {
    }

    public function handle(UpdateStockMovementCommand $command): StockMovementResponseDto
    {
        $movement = $this->movementRepository->findById(Uuid::fromString($command->id));
        if (null === $movement || !$movement->isEnabled()) {
            throw StockMovementNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $movement->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->quantity !== null) {
            $movement->setQuantity($command->quantity);
        }
        if ($command->direction !== null) {
            $movement->setDirection(StockMovementDirection::from($command->direction));
        }
        if ($command->clientId !== null) {
            $movement->setClientId($command->clientId !== '' ? Uuid::fromString($command->clientId) : null);
        }
        if ($command->projectId !== null) {
            $movement->setProjectId($command->projectId !== '' ? Uuid::fromString($command->projectId) : null);
        }
        if ($command->siteId !== null) {
            $movement->setSiteId($command->siteId !== '' ? Uuid::fromString($command->siteId) : null);
        }

        $this->movementRepository->save($movement);

        if ($command->lines !== null) {
            foreach ($this->lineRepository->findByMovementId($movement->getId()) as $existing) {
                $this->lineRepository->remove($existing);
            }
            foreach ($command->lines as $lineData) {
                $line = new StockMovementLine(
                    movement: $movement,
                    equipmentId: Uuid::fromString($lineData['equipmentId']),
                    quantity: (float) $lineData['quantity'],
                );
                $this->lineRepository->save($line);
            }
        }

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return StockMovementResponseDto::fromEntity($movement, $lines);
    }
}

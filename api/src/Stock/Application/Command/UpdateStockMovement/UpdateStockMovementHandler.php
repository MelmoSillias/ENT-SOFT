<?php

namespace App\Stock\Application\Command\UpdateStockMovement;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\StockMovementLineResponseDto;
use App\Stock\Application\Dto\StockMovementResponseDto;
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
        if ($command->unit !== null) {
            $movement->setUnit(FieldValidator::requireNonEmpty($command->unit, 'Unité'));
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

        $lines = array_map(
            static fn ($l) => StockMovementLineResponseDto::fromEntity($l)->toArray(),
            $this->lineRepository->findByMovementId($movement->getId()),
        );

        return new StockMovementResponseDto(
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
        );
    }
}

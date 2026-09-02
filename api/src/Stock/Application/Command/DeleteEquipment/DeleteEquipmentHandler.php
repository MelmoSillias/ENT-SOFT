<?php

namespace App\Stock\Application\Command\DeleteEquipment;

use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    public function handle(DeleteEquipmentCommand $command): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($command->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($command->id);
        }

        $equipment->disable();
        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

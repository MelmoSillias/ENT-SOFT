<?php

namespace App\Stock\Application\Command\UpdateEquipment;

use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Exception\EquipmentNotFoundException;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
    ) {
    }

    public function handle(UpdateEquipmentCommand $command): EquipmentResponseDto
    {
        $equipment = $this->equipmentRepository->findById(Uuid::fromString($command->id));
        if (null === $equipment || !$equipment->isEnabled()) {
            throw EquipmentNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $equipment->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->hasDescription) {
            $equipment->setDescription($command->description);
        }
        if ($command->hasClientId) {
            $equipment->setClientId($command->clientId !== null && $command->clientId !== ''
                ? Uuid::fromString($command->clientId)
                : null);
        }

        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

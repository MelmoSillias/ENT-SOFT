<?php

namespace App\Stock\Application\Command\CreateEquipment;

use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;
use App\Stock\Application\Dto\EquipmentResponseDto;
use App\Stock\Domain\Entity\Equipment;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateEquipmentHandler
{
    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateEquipmentCommand $command): EquipmentResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::EQUIPMENT);
        $equipment = new Equipment(
            code: $code,
            title: $title,
            description: $command->description,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
        );
        $this->equipmentRepository->save($equipment);

        return EquipmentResponseDto::fromEntity($equipment);
    }
}

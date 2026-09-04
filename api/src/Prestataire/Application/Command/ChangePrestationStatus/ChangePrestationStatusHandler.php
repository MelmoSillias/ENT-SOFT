<?php

namespace App\Prestataire\Application\Command\ChangePrestationStatus;

use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class ChangePrestationStatusHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(ChangePrestationStatusCommand $command): PrestationResponseDto
    {
        $prestation = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        $status = FieldValidator::requireNonEmpty($command->workStatus, 'Statut de travail');
        $prestation->setWorkStatus(PrestationWorkStatus::from($status));
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

<?php

namespace App\Prestataire\Application\Command\CreatePrestation;

use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Entity\Prestation;
use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreatePrestationHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(CreatePrestationCommand $command): PrestationResponseDto
    {
        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($command->prestataireId));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($command->prestataireId);
        }

        $description = FieldValidator::requireNonEmpty($command->description, 'Description');
        if ($command->amount <= 0) {
            throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
        }

        $workStatus = $command->workStatus !== null && $command->workStatus !== ''
            ? PrestationWorkStatus::from($command->workStatus)
            : PrestationWorkStatus::PENDING;

        $prestation = new Prestation(
            prestataireId: $prestataire->getId(),
            description: $description,
            amount: $command->amount,
            workStatus: $workStatus,
            paymentStatus: PrestationPaymentStatus::UNPAID,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
        );
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

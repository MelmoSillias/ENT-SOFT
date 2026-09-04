<?php

namespace App\Prestataire\Application\Command\DuplicatePrestation;

use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Entity\Prestation;
use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DuplicatePrestationHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(DuplicatePrestationCommand $command): PrestationResponseDto
    {
        $source = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $source || !$source->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        $copy = new Prestation(
            prestataireId: $source->getPrestataireId(),
            description: $source->getDescription(),
            amount: $source->getAmount(),
            workStatus: PrestationWorkStatus::PENDING,
            paymentStatus: PrestationPaymentStatus::UNPAID,
            siteId: $source->getSiteId(),
        );
        $this->prestationRepository->save($copy);

        return $this->assembler->toPrestationDto($copy);
    }
}

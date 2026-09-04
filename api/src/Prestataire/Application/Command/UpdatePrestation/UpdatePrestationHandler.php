<?php

namespace App\Prestataire\Application\Command\UpdatePrestation;

use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdatePrestationHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(UpdatePrestationCommand $command): PrestationResponseDto
    {
        $prestation = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        if ($command->description !== null) {
            $prestation->setDescription(FieldValidator::requireNonEmpty($command->description, 'Description'));
        }
        if ($command->amount !== null) {
            if ($command->amount <= 0) {
                throw new \InvalidArgumentException('Le montant doit être supérieur à 0.');
            }
            $prestation->setAmount($command->amount);
            $this->assembler->recalculatePaymentStatus($prestation);
        }
        if ($command->hasSiteId) {
            $prestation->setSiteId(
                $command->siteId !== null && $command->siteId !== ''
                    ? Uuid::fromString($command->siteId)
                    : null
            );
        }
        if ($command->workStatus !== null) {
            $prestation->setWorkStatus(PrestationWorkStatus::from($command->workStatus));
        }

        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

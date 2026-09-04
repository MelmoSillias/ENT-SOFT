<?php

namespace App\Prestataire\Application\Query\GetPrestation;

use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetPrestationHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(GetPrestationQuery $query): PrestationResponseDto
    {
        $prestation = $this->prestationRepository->findById(Uuid::fromString($query->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($query->id);
        }

        return $this->assembler->toPrestationDto($prestation);
    }
}

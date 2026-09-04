<?php

namespace App\Prestataire\Application\Query\GetPrestataire;

use App\Prestataire\Application\Dto\PrestataireResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetPrestataireHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(GetPrestataireQuery $query): PrestataireResponseDto
    {
        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($query->id));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($query->id);
        }

        return $this->assembler->toPrestataireDto($prestataire);
    }
}

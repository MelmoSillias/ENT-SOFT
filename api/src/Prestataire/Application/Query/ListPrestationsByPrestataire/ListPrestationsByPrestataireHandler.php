<?php

namespace App\Prestataire\Application\Query\ListPrestationsByPrestataire;

use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListPrestationsByPrestataireHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListPrestationsByPrestataireQuery $query): array
    {
        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($query->prestataireId));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($query->prestataireId);
        }

        return array_map(
            fn ($p) => $this->assembler->toPrestationDto($p)->toArray(),
            $this->prestationRepository->findByPrestataireId($prestataire->getId()),
        );
    }
}

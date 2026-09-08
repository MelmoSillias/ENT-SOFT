<?php

namespace App\Prestataire\Application\Query\ListAllPrestations;

use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;

final class ListAllPrestationsHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListAllPrestationsQuery $query): array
    {
        $nameById = [];
        foreach ($this->prestataireRepository->findAllEnabled() as $prestataire) {
            $nameById[(string) $prestataire->getId()] = $prestataire->getFullName();
        }

        $result = [];
        $prestations = $this->prestationRepository->findAllEnabled(
            $query->from ? new \DateTimeImmutable($query->from) : null,
            $query->to ? new \DateTimeImmutable($query->to) : null,
        );
        foreach ($prestations as $prestation) {
            $row = $this->assembler->toPrestationDto($prestation)->toArray();
            $row['prestataireName'] = $nameById[$row['prestataireId']] ?? null;
            $result[] = $row;
        }

        return $result;
    }
}

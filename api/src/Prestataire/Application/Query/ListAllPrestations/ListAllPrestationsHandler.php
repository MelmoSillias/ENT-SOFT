<?php

namespace App\Prestataire\Application\Query\ListAllPrestations;

use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use App\SharedKernel\Domain\Period\PeriodBounds;

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
        $photoById = [];
        foreach ($this->prestataireRepository->findAllEnabled() as $prestataire) {
            $id = (string) $prestataire->getId();
            $nameById[$id] = $prestataire->getFullName();
            $photoById[$id] = $prestataire->getPhotoUrl();
        }

        $result = [];
        $prestations = $this->prestationRepository->findAllEnabled(
            PeriodBounds::from($query->from),
            PeriodBounds::to($query->to),
        );
        foreach ($prestations as $prestation) {
            $row = $this->assembler->toPrestationDto($prestation)->toArray();
            $row['prestataireName'] = $nameById[$row['prestataireId']] ?? null;
            $row['prestatairePhotoUrl'] = $photoById[$row['prestataireId']] ?? null;
            $result[] = $row;
        }

        return $result;
    }
}

<?php

namespace App\Referentiel\Application\Service;

use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ResoudreTauxDefautService
{
    public function __construct(
        private readonly PaysRepositoryInterface $paysRepository,
        private readonly PaysDeviseLiaisonRepositoryInterface $liaisonRepository,
        private readonly DeviseRepositoryInterface $deviseRepository,
    ) {
    }

    public function resolve(Uuid $paysDestinationId, string $deviseSourceCode, string $deviseDestinationCode): ?string
    {
        $pays = $this->paysRepository->findById($paysDestinationId);
        if (null === $pays) {
            return null;
        }

        $deviseDest = $this->deviseRepository->findByCode($deviseDestinationCode);
        if (null === $deviseDest) {
            return null;
        }

        $liaison = $this->liaisonRepository->findByPaysAndDevise($pays->getId(), $deviseDest->getId());
        if (null !== $liaison) {
            return $liaison->getTauxDefaut();
        }

        return null;
    }
}

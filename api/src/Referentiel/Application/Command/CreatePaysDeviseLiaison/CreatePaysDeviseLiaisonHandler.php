<?php

namespace App\Referentiel\Application\Command\CreatePaysDeviseLiaison;

use App\Referentiel\Domain\Entity\HistoriqueTaux;
use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use App\Referentiel\Domain\Exception\PaysDeviseLiaisonConflictException;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use App\Referentiel\Domain\Repository\HistoriqueTauxRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysRepositoryInterface;
use App\SharedKernel\Domain\Exception\DomainException;

final class CreatePaysDeviseLiaisonHandler
{
    public function __construct(
        private readonly PaysDeviseLiaisonRepositoryInterface $liaisonRepository,
        private readonly PaysRepositoryInterface $paysRepository,
        private readonly DeviseRepositoryInterface $deviseRepository,
        private readonly HistoriqueTauxRepositoryInterface $historiqueTauxRepository,
    ) {
    }

    public function __invoke(CreatePaysDeviseLiaisonCommand $command): PaysDeviseLiaison
    {
        $pays = $this->paysRepository->findById($command->paysId);
        $devise = $this->deviseRepository->findById($command->deviseId);
        if (null === $pays || null === $devise) {
            throw new DomainException('Pays ou devise introuvable');
        }

        $existing = $this->liaisonRepository->findByPaysAndDevise($pays->getId(), $devise->getId());
        if (null !== $existing) {
            throw new PaysDeviseLiaisonConflictException();
        }

        $liaison = new PaysDeviseLiaison(
            pays: $pays,
            devise: $devise,
            tauxDefaut: $command->tauxDefaut,
            isDefaut: $command->isDefaut,
        );

        if ($liaison->isDefaut()) {
            $this->liaisonRepository->clearDefaultExcept(null, false);
        }

        $this->liaisonRepository->save($liaison, false);
        $this->historiqueTauxRepository->save(
            HistoriqueTaux::fromLiaison(
                $liaison,
                $command->utilisateurId,
                ancienTaux: null,
                motif: $command->motif,
            ),
        );

        return $liaison;
    }
}

<?php

namespace App\Referentiel\Application\Command\UpdatePaysDeviseLiaison;

use App\Referentiel\Domain\Entity\HistoriqueTaux;
use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use App\Referentiel\Domain\Exception\PaysDeviseLiaisonConflictException;
use App\Referentiel\Domain\Exception\PaysDeviseLiaisonNotFoundException;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use App\Referentiel\Domain\Repository\HistoriqueTauxRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\Referentiel\Domain\Repository\PaysRepositoryInterface;
use App\SharedKernel\Domain\Exception\DomainException;

final class UpdatePaysDeviseLiaisonHandler
{
    public function __construct(
        private readonly PaysDeviseLiaisonRepositoryInterface $liaisonRepository,
        private readonly PaysRepositoryInterface $paysRepository,
        private readonly DeviseRepositoryInterface $deviseRepository,
        private readonly HistoriqueTauxRepositoryInterface $historiqueTauxRepository,
    ) {
    }

    public function __invoke(UpdatePaysDeviseLiaisonCommand $command): PaysDeviseLiaison
    {
        $liaison = $this->liaisonRepository->findById($command->liaisonId);
        if (null === $liaison) {
            throw new PaysDeviseLiaisonNotFoundException($command->liaisonId);
        }

        $paysId = $command->paysId ?? $liaison->getPays()->getId();
        $deviseId = $command->deviseId ?? $liaison->getDevise()->getId();

        $duplicate = $this->liaisonRepository->findByPaysAndDevise($paysId, $deviseId);
        if (null !== $duplicate && !$duplicate->getId()->equals($liaison->getId())) {
            throw new PaysDeviseLiaisonConflictException();
        }

        if (null !== $command->paysId) {
            $pays = $this->paysRepository->findById($command->paysId);
            if (null === $pays) {
                throw new DomainException('Pays introuvable');
            }
            $liaison->setPays($pays);
        }

        if (null !== $command->deviseId) {
            $devise = $this->deviseRepository->findById($command->deviseId);
            if (null === $devise) {
                throw new DomainException('Devise introuvable');
            }
            $liaison->setDevise($devise);
        }

        $ancienTaux = $liaison->getTauxDefaut();
        $tauxChanged = false;

        if (null !== $command->tauxDefaut && 0 !== bccomp($command->tauxDefaut, $ancienTaux, 6)) {
            $liaison->setTauxDefaut($command->tauxDefaut);
            $tauxChanged = true;
        }

        if (null !== $command->isDefaut) {
            $liaison->setIsDefaut($command->isDefaut);
        }

        if ($liaison->isDefaut()) {
            $this->liaisonRepository->clearDefaultExcept($liaison->getId(), false);
        }

        if ($tauxChanged) {
            $this->liaisonRepository->save($liaison, false);
            $this->historiqueTauxRepository->save(
                HistoriqueTaux::fromLiaison(
                    $liaison,
                    $command->utilisateurId,
                    ancienTaux: $ancienTaux,
                    motif: $command->motif,
                ),
            );
        } else {
            $this->liaisonRepository->save($liaison);
        }

        return $liaison;
    }
}

<?php

namespace App\Referentiel\Application\Query\GetHistoriqueTaux;

use App\Referentiel\Domain\Entity\HistoriqueTaux;
use App\Referentiel\Domain\Repository\HistoriqueTauxRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetHistoriqueTauxHandler
{
    public function __construct(
        private readonly HistoriqueTauxRepositoryInterface $historiqueTauxRepository,
    ) {
    }

    /**
     * @return list<HistoriqueTaux>
     */
    public function handle(?Uuid $liaisonId = null): array
    {
        if (null !== $liaisonId) {
            return $this->historiqueTauxRepository->findByLiaisonId($liaisonId);
        }

        return $this->historiqueTauxRepository->findAllOrdered();
    }
}

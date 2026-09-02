<?php

namespace App\Referentiel\Domain\Repository;

use App\Referentiel\Domain\Entity\HistoriqueTaux;
use Symfony\Component\Uid\Uuid;

interface HistoriqueTauxRepositoryInterface
{
    /** @return list<HistoriqueTaux> */
    public function findAllOrdered(): array;

    /** @return list<HistoriqueTaux> */
    public function findByLiaisonId(Uuid $liaisonId): array;

    public function save(HistoriqueTaux $historiqueTaux, bool $flush = true): void;
}

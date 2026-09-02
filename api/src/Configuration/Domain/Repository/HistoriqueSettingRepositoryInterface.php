<?php

namespace App\Configuration\Domain\Repository;

use App\Configuration\Domain\Entity\HistoriqueSetting;

interface HistoriqueSettingRepositoryInterface
{
    /** @return list<HistoriqueSetting> */
    public function findBySettingCle(string $cle): array;

    public function save(HistoriqueSetting $historiqueSetting, bool $flush = true): void;
}

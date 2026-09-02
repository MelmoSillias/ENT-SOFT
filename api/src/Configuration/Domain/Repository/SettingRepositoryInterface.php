<?php

namespace App\Configuration\Domain\Repository;

use App\Configuration\Domain\Entity\Setting;

interface SettingRepositoryInterface
{
    public function findByCle(string $cle): ?Setting;

    /** @return list<Setting> */
    public function findAll(): array;

    public function save(Setting $setting, bool $flush = true): void;
}

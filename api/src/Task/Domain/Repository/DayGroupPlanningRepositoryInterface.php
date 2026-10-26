<?php

namespace App\Task\Domain\Repository;

use App\Task\Domain\Entity\DayGroupPlanning;

interface DayGroupPlanningRepositoryInterface
{
    public function save(DayGroupPlanning $planning): void;

    public function findByDate(\DateTimeImmutable $date): ?DayGroupPlanning;

    public function findLatestBefore(\DateTimeImmutable $date): ?DayGroupPlanning;
}

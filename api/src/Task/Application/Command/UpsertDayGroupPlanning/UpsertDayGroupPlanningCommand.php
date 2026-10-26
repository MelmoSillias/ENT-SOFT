<?php

namespace App\Task\Application\Command\UpsertDayGroupPlanning;

final readonly class UpsertDayGroupPlanningCommand
{
    public function __construct(
        public string $date,
        public mixed $groups,
    ) {
    }
}

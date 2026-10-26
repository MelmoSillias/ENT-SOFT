<?php

namespace App\Task\Application\Query\GetDayGroupPlanning;

final readonly class GetDayGroupPlanningQuery
{
    public function __construct(
        public string $date,
    ) {
    }
}

<?php

namespace App\Employee\Application\Query\ListEmployeePositions;

final readonly class ListEmployeePositionsQuery
{
    public function __construct(
        public bool $latestOnly = false,
        public ?string $employeeId = null,
        public ?int $limit = null,
    ) {
    }
}

<?php

namespace App\Task\Application\Query\ListTasks;

final readonly class ListTasksQuery
{
    public function __construct(
        public ?string $siteId = null,
        public ?string $employeeId = null,
        public ?string $status = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}

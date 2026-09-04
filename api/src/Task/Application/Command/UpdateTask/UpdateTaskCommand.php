<?php

namespace App\Task\Application\Command\UpdateTask;

final readonly class UpdateTaskCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $dateDue = null,
        public ?string $status = null,
        public ?string $siteId = null,
        public ?string $employeeId = null,
        public bool $hasDescription = false,
        public bool $hasDateDue = false,
        public bool $hasEmployeeId = false,
    ) {
    }
}

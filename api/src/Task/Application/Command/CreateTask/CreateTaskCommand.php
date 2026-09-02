<?php

namespace App\Task\Application\Command\CreateTask;

final readonly class CreateTaskCommand
{
    public function __construct(
        public string $title,
        public string $siteId,
        public string $status = 'pending',
        public ?string $description = null,
        public ?string $dateDue = null,
        public ?string $employeeId = null,
    ) {
    }
}

<?php

namespace App\Project\Application\Command\UpdateProjectSite;

final readonly class UpdateProjectSiteCommand
{
    public function __construct(
        public string $id,
        public ?string $status = null,
        public ?array $informationsValues = null,
        public ?array $employeeIds = null,
    ) {
    }
}

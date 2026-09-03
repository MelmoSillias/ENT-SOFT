<?php

namespace App\Project\Application\Command\AddSiteToProject;

final readonly class AddSiteToProjectCommand
{
    public function __construct(
        public string $projectId,
        public string $siteId,
        public string $status = 'pending',
        public array $informationsValues = [],
        public array $employeeIds = [],
        public ?string $lotId = null,
        public ?string $technicianId = null,
    ) {
    }
}

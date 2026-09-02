<?php

namespace App\Project\Application\Command\CreateProject;

final readonly class CreateProjectCommand
{
    public function __construct(
        public string $title,
        public string $clientId,
        public ?string $object = null,
        public ?string $dateDebut = null,
        public ?string $dateFin = null,
        public string $status = 'draft',
        public float $budget = 0.0,
        public array $sitesInformations = [],
    ) {
    }
}

<?php

namespace App\Project\Application\Command\UpdateProject;

final readonly class UpdateProjectCommand
{
    public function __construct(
        public string $id,
        public ?string $title = null,
        public ?string $object = null,
        public ?string $dateDebut = null,
        public ?string $dateFin = null,
        public ?string $status = null,
        public ?float $budget = null,
        public ?string $clientId = null,
        public ?array $sitesInformations = null,
        public bool $hasObject = false,
        public bool $hasDateDebut = false,
        public bool $hasDateFin = false,
    ) {
    }
}

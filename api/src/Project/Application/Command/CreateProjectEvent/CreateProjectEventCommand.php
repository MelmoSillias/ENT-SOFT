<?php

namespace App\Project\Application\Command\CreateProjectEvent;

final readonly class CreateProjectEventCommand
{
    public function __construct(
        public string $projectId,
        public string $date,
        public string $title,
    ) {
    }
}

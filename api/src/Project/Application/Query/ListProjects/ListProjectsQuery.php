<?php

namespace App\Project\Application\Query\ListProjects;

final readonly class ListProjectsQuery
{
    public function __construct(
        public ?string $search = null,
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}

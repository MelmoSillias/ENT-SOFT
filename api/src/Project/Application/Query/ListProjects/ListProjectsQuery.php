<?php

namespace App\Project\Application\Query\ListProjects;

final readonly class ListProjectsQuery
{
    public function __construct(public ?string $search = null) {}
}

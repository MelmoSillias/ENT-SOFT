<?php

namespace App\Project\Application\Command\RemoveSiteFromProject;

final readonly class RemoveSiteFromProjectCommand
{
    public function __construct(public string $id) {}
}

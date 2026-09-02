<?php

namespace App\Project\Application\Command\DeleteProject;

final readonly class DeleteProjectCommand
{
    public function __construct(public string $id) {}
}

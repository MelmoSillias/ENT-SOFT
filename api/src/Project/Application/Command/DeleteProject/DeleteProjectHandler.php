<?php

namespace App\Project\Application\Command\DeleteProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(DeleteProjectCommand $command): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($command->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->id);
        }

        $project->disable();
        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

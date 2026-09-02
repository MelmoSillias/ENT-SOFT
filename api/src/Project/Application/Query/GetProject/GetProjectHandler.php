<?php

namespace App\Project\Application\Query\GetProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(GetProjectQuery $query): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($query->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($query->id);
        }

        return ProjectResponseDto::fromEntity($project);
    }
}

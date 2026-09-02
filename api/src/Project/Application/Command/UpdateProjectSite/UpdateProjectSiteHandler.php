<?php

namespace App\Project\Application\Command\UpdateProjectSite;

use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Domain\Exception\ProjectSiteNotFoundException;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateProjectSiteHandler
{
    public function __construct(
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(UpdateProjectSiteCommand $command): ProjectSiteResponseDto
    {
        $projectSite = $this->projectSiteRepository->findById(Uuid::fromString($command->id));
        if (null === $projectSite) {
            throw ProjectSiteNotFoundException::withId($command->id);
        }

        if ($command->status !== null) {
            $projectSite->setStatus(ProjectSiteStatus::from($command->status));
        }
        if ($command->informationsValues !== null) {
            $projectSite->setInformationsValues($command->informationsValues);
        }
        if ($command->employeeIds !== null) {
            $projectSite->setEmployeeIds($command->employeeIds);
        }

        $this->projectSiteRepository->save($projectSite);

        return ProjectSiteResponseDto::fromEntity($projectSite);
    }
}

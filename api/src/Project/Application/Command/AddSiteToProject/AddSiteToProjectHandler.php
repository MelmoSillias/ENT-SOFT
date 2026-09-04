<?php

namespace App\Project\Application\Command\AddSiteToProject;

use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Application\Service\SitesInformationsNormalizer;
use App\Project\Domain\Entity\ProjectSite;
use App\Project\Domain\Enum\ProjectSiteStatus;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class AddSiteToProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(AddSiteToProjectCommand $command): ProjectSiteResponseDto
    {
        $projectId = Uuid::fromString($command->projectId);
        $project = $this->projectRepository->findById($projectId);
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->projectId);
        }

        $siteId = Uuid::fromString($command->siteId);
        if ($this->projectSiteRepository->findByProjectAndSite($projectId, $siteId) !== null) {
            throw new \InvalidArgumentException('Ce site est déjà associé au projet.');
        }

        $technicianId = $command->technicianId !== null && $command->technicianId !== ''
            ? Uuid::fromString($command->technicianId)
            : null;
        $lotId = $command->lotId !== null && $command->lotId !== ''
            ? Uuid::fromString($command->lotId)
            : null;

        $employeeIds = $command->employeeIds;
        if ($technicianId !== null) {
            $techStr = (string) $technicianId;
            if (!in_array($techStr, $employeeIds, true)) {
                $employeeIds[] = $techStr;
            }
        }

        $projectSite = new ProjectSite(
            projectId: $projectId,
            siteId: $siteId,
            status: ProjectSiteStatus::from($command->status),
            informationsValues: SitesInformationsNormalizer::normalizeValues($command->informationsValues),
            employeeIds: $employeeIds,
            lotId: $lotId,
            technicianId: $technicianId,
        );
        $this->projectSiteRepository->save($projectSite);

        return ProjectSiteResponseDto::fromEntity($projectSite);
    }
}

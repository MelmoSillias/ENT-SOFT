<?php

namespace App\Project\Application\Command\UpdateProjectSite;

use App\Project\Application\Dto\ProjectSiteResponseDto;
use App\Project\Application\Service\SitesInformationsNormalizer;
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
            // Merge so orphan keys (removed from project sitesInformations) are preserved.
            $merged = array_merge(
                $projectSite->getInformationsValues(),
                SitesInformationsNormalizer::normalizeValues($command->informationsValues),
            );
            $projectSite->setInformationsValues($merged);
        }
        if ($command->employeeIds !== null) {
            $projectSite->setEmployeeIds($command->employeeIds);
        }
        if ($command->clearLotId) {
            $projectSite->setLotId(null);
        } elseif ($command->lotId !== null) {
            $projectSite->setLotId($command->lotId !== '' ? Uuid::fromString($command->lotId) : null);
        }
        if ($command->clearTechnicianId) {
            $projectSite->setTechnicianId(null);
        } elseif ($command->technicianId !== null) {
            $techId = $command->technicianId !== '' ? Uuid::fromString($command->technicianId) : null;
            $projectSite->setTechnicianId($techId);
            if ($techId !== null) {
                $ids = $projectSite->getEmployeeIds();
                $techStr = (string) $techId;
                if (!in_array($techStr, $ids, true)) {
                    $ids[] = $techStr;
                    $projectSite->setEmployeeIds($ids);
                }
            }
        }

        $this->projectSiteRepository->save($projectSite);

        return ProjectSiteResponseDto::fromEntity($projectSite);
    }
}

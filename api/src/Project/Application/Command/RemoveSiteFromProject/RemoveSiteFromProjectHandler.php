<?php

namespace App\Project\Application\Command\RemoveSiteFromProject;

use App\Project\Domain\Exception\ProjectSiteNotFoundException;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class RemoveSiteFromProjectHandler
{
    public function __construct(
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    public function handle(RemoveSiteFromProjectCommand $command): void
    {
        $projectSite = $this->projectSiteRepository->findById(Uuid::fromString($command->id));
        if (null === $projectSite) {
            throw ProjectSiteNotFoundException::withId($command->id);
        }

        $this->projectSiteRepository->remove($projectSite);
    }
}

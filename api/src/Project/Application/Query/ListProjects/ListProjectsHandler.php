<?php

namespace App\Project\Application\Query\ListProjects;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;

final class ListProjectsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectSiteRepositoryInterface $projectSiteRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListProjectsQuery $query): array
    {
        $projects = $this->projectRepository->findAllEnabled($query->search);
        $siteCounts = $this->projectSiteRepository->countByProjectIds(
            array_map(static fn ($p) => $p->getId(), $projects),
        );

        return array_map(
            static fn ($p) => ProjectResponseDto::fromEntity(
                $p,
                $siteCounts[(string) $p->getId()] ?? 0,
            )->toArray(),
            $projects,
        );
    }
}

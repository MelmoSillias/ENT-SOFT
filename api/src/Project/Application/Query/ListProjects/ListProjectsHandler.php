<?php

namespace App\Project\Application\Query\ListProjects;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Repository\ProjectRepositoryInterface;

final class ListProjectsHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListProjectsQuery $query): array
    {
        return array_map(
            static fn ($p) => ProjectResponseDto::fromEntity($p)->toArray(),
            $this->projectRepository->findAllEnabled($query->search),
        );
    }
}

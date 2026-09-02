<?php

namespace App\Site\Application\Query\ListSites;

use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Repository\SiteRepositoryInterface;

final class ListSitesHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListSitesQuery $query): array
    {
        return array_map(
            static fn ($item) => SiteResponseDto::fromEntity($item)->toArray(),
            $this->siteRepository->findAllEnabled($query->search),
        );
    }
}

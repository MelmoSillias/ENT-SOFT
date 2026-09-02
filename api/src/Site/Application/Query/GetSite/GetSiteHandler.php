<?php

namespace App\Site\Application\Query\GetSite;

use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Exception\SiteNotFoundException;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetSiteHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    public function handle(GetSiteQuery $query): SiteResponseDto
    {
        $site = $this->siteRepository->findById(Uuid::fromString($query->id));
        if (null === $site || !$site->isEnabled()) {
            throw SiteNotFoundException::withId($query->id);
        }

        return SiteResponseDto::fromEntity($site);
    }
}

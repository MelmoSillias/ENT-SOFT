<?php

namespace App\Site\Application\Command\DeleteSite;

use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Exception\SiteNotFoundException;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteSiteHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    public function handle(DeleteSiteCommand $command): SiteResponseDto
    {
        $site = $this->siteRepository->findById(Uuid::fromString($command->id));
        if (null === $site || !$site->isEnabled()) {
            throw SiteNotFoundException::withId($command->id);
        }

        $site->disable();
        $this->siteRepository->save($site);

        return SiteResponseDto::fromEntity($site);
    }
}

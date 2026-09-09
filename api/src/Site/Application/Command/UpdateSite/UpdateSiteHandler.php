<?php

namespace App\Site\Application\Command\UpdateSite;

use App\Geo\Domain\ValueObject\GeoPoint;
use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Exception\SiteNotFoundException;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateSiteHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    public function handle(UpdateSiteCommand $command): SiteResponseDto
    {
        $site = $this->siteRepository->findById(Uuid::fromString($command->id));
        if (null === $site || !$site->isEnabled()) {
            throw SiteNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $site->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->hasDescription) {
            $site->setDescription($command->description);
        }
        if ($command->hasClientId) {
            $site->setClientId($command->clientId !== null && $command->clientId !== ''
                ? Uuid::fromString($command->clientId)
                : null);
        }
        if ($command->hasLocation) {
            $point = GeoPoint::tryFrom($command->latitude, $command->longitude);
            $site->setLocation($point?->latitude, $point?->longitude);
        }

        $this->siteRepository->save($site);

        return SiteResponseDto::fromEntity($site);
    }
}

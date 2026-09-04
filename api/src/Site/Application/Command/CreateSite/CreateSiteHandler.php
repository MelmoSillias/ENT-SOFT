<?php

namespace App\Site\Application\Command\CreateSite;

use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Entity\Site;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateSiteHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
    ) {
    }

    public function handle(CreateSiteCommand $command): SiteResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = FieldValidator::requireNonEmpty($command->code, 'Code');

        $existing = $this->siteRepository->findByCode($code);
        if (null !== $existing) {
            throw new \InvalidArgumentException(sprintf(
                'Un site existe déjà avec ce code : %s — %s',
                $existing->getCode(),
                $existing->getTitle(),
            ));
        }

        $site = new Site(
            $code,
            $title,
            $command->description,
            $command->clientId !== null ? Uuid::fromString($command->clientId) : null,
        );
        $this->siteRepository->save($site);

        return SiteResponseDto::fromEntity($site);
    }
}

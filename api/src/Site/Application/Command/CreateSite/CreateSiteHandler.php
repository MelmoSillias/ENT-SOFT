<?php

namespace App\Site\Application\Command\CreateSite;

use App\Site\Application\Dto\SiteResponseDto;
use App\Site\Domain\Entity\Site;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateSiteHandler
{
    public function __construct(
        private readonly SiteRepositoryInterface $siteRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateSiteCommand $command): SiteResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::SITE);
        $site = new Site($code, $title, $command->description, $command->clientId !== null ? Uuid::fromString($command->clientId) : null);
        $this->siteRepository->save($site);

        return SiteResponseDto::fromEntity($site);
    }
}

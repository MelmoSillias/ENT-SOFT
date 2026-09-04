<?php

namespace App\Project\Application\Command\CreateProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Application\Service\SitesInformationsNormalizer;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\Referentiel\Application\Service\CodeGeneratorService;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly CodeGeneratorService $codeGenerator,
    ) {
    }

    public function handle(CreateProjectCommand $command): ProjectResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $code = $this->codeGenerator->generate(ReferenceSequenceType::PROJECT);
        $project = new Project(
            code: $code,
            title: $title,
            clientId: Uuid::fromString($command->clientId),
            status: ProjectStatus::from($command->status),
            object: $command->object,
            dateDebut: $command->dateDebut ? new \DateTimeImmutable($command->dateDebut) : null,
            dateFin: $command->dateFin ? new \DateTimeImmutable($command->dateFin) : null,
            budget: $command->budget,
            sitesInformations: SitesInformationsNormalizer::normalizeDefinitions($command->sitesInformations),
        );
        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

<?php

namespace App\Project\Application\Command\UpdateProject;

use App\Project\Application\Dto\ProjectResponseDto;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateProjectHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {
    }

    public function handle(UpdateProjectCommand $command): ProjectResponseDto
    {
        $project = $this->projectRepository->findById(Uuid::fromString($command->id));
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $project->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->object !== null) {
            $project->setObject($command->object);
        }
        if ($command->dateDebut !== null) {
            $project->setDateDebut(new \DateTimeImmutable($command->dateDebut));
        }
        if ($command->dateFin !== null) {
            $project->setDateFin(new \DateTimeImmutable($command->dateFin));
        }
        if ($command->status !== null) {
            $project->setStatus(ProjectStatus::from($command->status));
        }
        if ($command->budget !== null) {
            $project->setBudget($command->budget);
        }
        if ($command->clientId !== null) {
            $project->setClientId(Uuid::fromString($command->clientId));
        }
        if ($command->sitesInformations !== null) {
            $project->setSitesInformations($command->sitesInformations);
        }

        $this->projectRepository->save($project);

        return ProjectResponseDto::fromEntity($project);
    }
}

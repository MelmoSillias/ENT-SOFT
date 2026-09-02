<?php

namespace App\Project\Application\Command\CreateProjectEvent;

use App\Project\Application\Dto\ProjectEventResponseDto;
use App\Project\Domain\Entity\ProjectEvent;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateProjectEventHandler
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly ProjectEventRepositoryInterface $projectEventRepository,
    ) {
    }

    public function handle(CreateProjectEventCommand $command): ProjectEventResponseDto
    {
        $projectId = Uuid::fromString($command->projectId);
        $project = $this->projectRepository->findById($projectId);
        if (null === $project || !$project->isEnabled()) {
            throw ProjectNotFoundException::withId($command->projectId);
        }

        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $event = new ProjectEvent($projectId, new \DateTimeImmutable($command->date), $title);
        $this->projectEventRepository->save($event);

        return ProjectEventResponseDto::fromEntity($event);
    }
}

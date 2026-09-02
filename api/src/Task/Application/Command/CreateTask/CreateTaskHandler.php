<?php

namespace App\Task\Application\Command\CreateTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(CreateTaskCommand $command): TaskResponseDto
    {
        $title = FieldValidator::requireNonEmpty($command->title, 'Titre');
        $task = new Task(
            title: $title,
            siteId: Uuid::fromString($command->siteId),
            status: TaskStatus::from($command->status),
            description: $command->description,
            dateDue: $command->dateDue ? new \DateTimeImmutable($command->dateDue) : null,
            employeeId: $command->employeeId ? Uuid::fromString($command->employeeId) : null,
        );
        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

<?php

namespace App\Task\Application\Command\UpdateTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(UpdateTaskCommand $command): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($command->id);
        }

        if ($command->title !== null) {
            $task->setTitle(FieldValidator::requireNonEmpty($command->title, 'Titre'));
        }
        if ($command->description !== null) {
            $task->setDescription($command->description);
        }
        if ($command->dateDue !== null) {
            $task->setDateDue(new \DateTimeImmutable($command->dateDue));
        }
        if ($command->status !== null) {
            $task->setStatus(TaskStatus::from($command->status));
        }
        if ($command->siteId !== null) {
            $task->setSiteId(Uuid::fromString($command->siteId));
        }
        if ($command->employeeId !== null) {
            $task->setEmployeeId($command->employeeId !== '' ? Uuid::fromString($command->employeeId) : null);
        }

        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

<?php

namespace App\Task\Application\Command\DeleteTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(DeleteTaskCommand $command): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($command->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($command->id);
        }

        $task->disable();
        $this->taskRepository->save($task);

        return TaskResponseDto::fromEntity($task);
    }
}

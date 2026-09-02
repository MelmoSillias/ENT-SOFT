<?php

namespace App\Task\Application\Query\GetTask;

use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Exception\TaskNotFoundException;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    public function handle(GetTaskQuery $query): TaskResponseDto
    {
        $task = $this->taskRepository->findById(Uuid::fromString($query->id));
        if (null === $task || !$task->isEnabled()) {
            throw TaskNotFoundException::withId($query->id);
        }

        return TaskResponseDto::fromEntity($task);
    }
}

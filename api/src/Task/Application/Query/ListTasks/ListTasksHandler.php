<?php

namespace App\Task\Application\Query\ListTasks;

use App\SharedKernel\Domain\Period\PeriodBounds;
use App\Task\Application\Dto\TaskResponseDto;
use App\Task\Domain\Enum\TaskStatus;
use App\Task\Domain\Repository\TaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ListTasksHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListTasksQuery $query): array
    {
        $tasks = $this->taskRepository->findFiltered(
            siteId: $query->siteId ? Uuid::fromString($query->siteId) : null,
            employeeId: $query->employeeId ? Uuid::fromString($query->employeeId) : null,
            status: $query->status ? TaskStatus::from($query->status) : null,
            from: PeriodBounds::from($query->from),
            to: PeriodBounds::to($query->to),
        );

        return array_map(
            static fn ($t) => TaskResponseDto::fromEntity($t)->toArray(),
            $tasks,
        );
    }
}

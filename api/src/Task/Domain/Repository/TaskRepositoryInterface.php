<?php

namespace App\Task\Domain\Repository;

use App\Task\Domain\Entity\Task;
use App\Task\Domain\Enum\TaskStatus;
use Symfony\Component\Uid\Uuid;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(Uuid $id): ?Task;

    /** @return list<Task> */
    public function findFiltered(
        ?Uuid $siteId = null,
        ?Uuid $employeeId = null,
        ?TaskStatus $status = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array;

    public function countDueToday(): int;
}

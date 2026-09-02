<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use Symfony\Component\Uid\Uuid;

interface ProjectRepositoryInterface
{
    public function save(Project $project): void;

    public function findById(Uuid $id): ?Project;

    /** @return list<Project> */
    public function findAllEnabled(?string $search = null): array;

    public function countByClientId(Uuid $clientId): int;

    public function countByStatus(ProjectStatus $status): int;
}

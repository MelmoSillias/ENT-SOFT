<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectEvent;
use Symfony\Component\Uid\Uuid;

interface ProjectEventRepositoryInterface
{
    public function save(ProjectEvent $event): void;

    /** @return list<ProjectEvent> */
    public function findByProjectId(Uuid $projectId): array;
}

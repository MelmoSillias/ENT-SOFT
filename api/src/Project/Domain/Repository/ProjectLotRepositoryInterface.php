<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectLot;
use Symfony\Component\Uid\Uuid;

interface ProjectLotRepositoryInterface
{
    public function save(ProjectLot $lot): void;

    public function findById(Uuid $id): ?ProjectLot;

    /** @return list<ProjectLot> */
    public function findByProjectId(Uuid $projectId): array;

    public function findByProjectAndCode(Uuid $projectId, string $code): ?ProjectLot;
}

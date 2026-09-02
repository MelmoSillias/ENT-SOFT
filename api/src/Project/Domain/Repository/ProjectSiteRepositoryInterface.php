<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectSite;
use Symfony\Component\Uid\Uuid;

interface ProjectSiteRepositoryInterface
{
    public function save(ProjectSite $projectSite): void;

    public function findById(Uuid $id): ?ProjectSite;

    /** @return list<ProjectSite> */
    public function findByProjectId(Uuid $projectId): array;

    public function findByProjectAndSite(Uuid $projectId, Uuid $siteId): ?ProjectSite;

    public function remove(ProjectSite $projectSite): void;
}

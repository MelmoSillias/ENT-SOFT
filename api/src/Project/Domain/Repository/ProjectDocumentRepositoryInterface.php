<?php

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectDocument;
use Symfony\Component\Uid\Uuid;

interface ProjectDocumentRepositoryInterface
{
    public function save(ProjectDocument $document): void;
    public function remove(ProjectDocument $document): void;
    public function findById(Uuid $id): ?ProjectDocument;
    /** @return list<ProjectDocument> */
    public function findByProjectId(Uuid $projectId): array;
}

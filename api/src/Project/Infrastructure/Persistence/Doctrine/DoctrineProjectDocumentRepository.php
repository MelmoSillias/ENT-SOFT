<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectDocument;
use App\Project\Domain\Repository\ProjectDocumentRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectDocument> */
final class DoctrineProjectDocumentRepository extends ServiceEntityRepository implements ProjectDocumentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectDocument::class);
    }

    public function save(ProjectDocument $document): void
    {
        $this->getEntityManager()->persist($document);
        $this->getEntityManager()->flush();
    }

    public function remove(ProjectDocument $document): void
    {
        $this->getEntityManager()->remove($document);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ProjectDocument
    {
        return $this->find($id);
    }

    public function findByProjectId(Uuid $projectId): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.date', 'DESC')->addOrderBy('d.id', 'DESC');
        UuidQueryParameter::eq($qb, 'd.projectId', 'projectId', $projectId);

        return $qb->getQuery()->getResult();
    }
}

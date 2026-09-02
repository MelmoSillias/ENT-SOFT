<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectSite;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectSite> */
class DoctrineProjectSiteRepository extends ServiceEntityRepository implements ProjectSiteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectSite::class);
    }

    public function save(ProjectSite $projectSite): void
    {
        $this->getEntityManager()->persist($projectSite);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ProjectSite
    {
        return $this->find($id);
    }

    public function findByProjectId(Uuid $projectId): array
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('ps.dateAdded', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProjectAndSite(Uuid $projectId, Uuid $siteId): ?ProjectSite
    {
        return $this->createQueryBuilder('ps')
            ->andWhere('ps.projectId = :projectId')
            ->andWhere('ps.siteId = :siteId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->setParameter('siteId', $siteId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function remove(ProjectSite $projectSite): void
    {
        $this->getEntityManager()->remove($projectSite);
        $this->getEntityManager()->flush();
    }
}

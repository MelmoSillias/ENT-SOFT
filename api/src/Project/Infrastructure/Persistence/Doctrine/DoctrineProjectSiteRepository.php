<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectSite;
use App\Project\Domain\Repository\ProjectSiteRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
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
        $qb = $this->createQueryBuilder('e');
        UuidQueryParameter::eq($qb, 'e.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByProjectId(Uuid $projectId): array
    {
        $qb = $this->createQueryBuilder('ps')
            ->orderBy('ps.dateAdded', 'ASC');
        UuidQueryParameter::eq($qb, 'ps.projectId', 'projectId', $projectId);

        return $qb->getQuery()->getResult();
    }

    public function findByProjectAndSite(Uuid $projectId, Uuid $siteId): ?ProjectSite
    {
        $qb = $this->createQueryBuilder('ps');
        UuidQueryParameter::eq($qb, 'ps.projectId', 'projectId', $projectId);
        UuidQueryParameter::eq($qb, 'ps.siteId', 'siteId', $siteId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function remove(ProjectSite $projectSite): void
    {
        $this->getEntityManager()->remove($projectSite);
        $this->getEntityManager()->flush();
    }

    public function countByProjectIds(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('ps')
            ->select('ps.projectId AS projectId, COUNT(ps.id) AS siteCount')
            ->groupBy('ps.projectId');
        UuidQueryParameter::in($qb, 'ps.projectId', 'projectIds', $projectIds);

        $counts = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $counts[(string) $row['projectId']] = (int) $row['siteCount'];
        }

        return $counts;
    }
}

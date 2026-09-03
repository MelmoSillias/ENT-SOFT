<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\Project;
use App\Project\Domain\Enum\ProjectStatus;
use App\Project\Domain\Repository\ProjectRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Project> */
class DoctrineProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $project): void
    {
        $this->getEntityManager()->persist($project);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Project
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('p.title LIKE :search OR p.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByClientId(Uuid $clientId): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.clientId = :clientId')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true);
        UuidQueryParameter::bind($qb, 'clientId', $clientId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countByStatus(ProjectStatus $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('status', $status)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

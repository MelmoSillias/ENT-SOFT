<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectEvent;
use App\Project\Domain\Repository\ProjectEventRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectEvent> */
class DoctrineProjectEventRepository extends ServiceEntityRepository implements ProjectEventRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectEvent::class);
    }

    public function save(ProjectEvent $event): void
    {
        $this->getEntityManager()->persist($event);
        $this->getEntityManager()->flush();
    }

    public function findByProjectId(Uuid $projectId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

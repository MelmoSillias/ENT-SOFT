<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectLot;
use App\Project\Domain\Repository\ProjectLotRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ProjectLot> */
class DoctrineProjectLotRepository extends ServiceEntityRepository implements ProjectLotRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectLot::class);
    }

    public function save(ProjectLot $lot): void
    {
        $this->getEntityManager()->persist($lot);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ProjectLot
    {
        return $this->find($id);
    }

    public function findByProjectId(Uuid $projectId): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->setParameter('projectId', $projectId, 'uuid')
            ->orderBy('l.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProjectAndCode(Uuid $projectId, string $code): ?ProjectLot
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->andWhere('l.code = :code')
            ->setParameter('projectId', $projectId, 'uuid')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

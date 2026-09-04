<?php

namespace App\Project\Infrastructure\Persistence\Doctrine;

use App\Project\Domain\Entity\ProjectLot;
use App\Project\Domain\Repository\ProjectLotRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
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
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByProjectId(Uuid $projectId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->orderBy('l.code', 'ASC');
        UuidQueryParameter::bind($qb, 'projectId', $projectId);

        return $qb->getQuery()->getResult();
    }

    public function findByProjectAndCode(Uuid $projectId, string $code): ?ProjectLot
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.projectId = :projectId')
            ->andWhere('l.code = :code')
            ->setParameter('code', $code);
        UuidQueryParameter::bind($qb, 'projectId', $projectId);

        return $qb->getQuery()->getOneOrNullResult();
    }
}

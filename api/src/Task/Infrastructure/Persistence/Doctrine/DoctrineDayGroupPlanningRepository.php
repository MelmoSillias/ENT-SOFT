<?php

namespace App\Task\Infrastructure\Persistence\Doctrine;

use App\Task\Domain\Entity\DayGroupPlanning;
use App\Task\Domain\Repository\DayGroupPlanningRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DayGroupPlanning> */
class DoctrineDayGroupPlanningRepository extends ServiceEntityRepository implements DayGroupPlanningRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DayGroupPlanning::class);
    }

    public function save(DayGroupPlanning $planning): void
    {
        $this->getEntityManager()->persist($planning);
        $this->getEntityManager()->flush();
    }

    public function findByDate(\DateTimeImmutable $date): ?DayGroupPlanning
    {
        $start = $date->setTime(0, 0);
        $end = $start->modify('+1 day');

        return $this->createQueryBuilder('p')
            ->andWhere('p.planDate >= :start')
            ->andWhere('p.planDate < :end')
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

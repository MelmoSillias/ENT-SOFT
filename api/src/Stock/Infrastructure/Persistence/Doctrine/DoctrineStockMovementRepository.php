<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Stock\Domain\Entity\StockMovement;
use App\Stock\Domain\Repository\StockMovementRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<StockMovement> */
class DoctrineStockMovementRepository extends ServiceEntityRepository implements StockMovementRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovement::class);
    }

    public function save(StockMovement $movement): void
    {
        $this->getEntityManager()->persist($movement);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?StockMovement
    {
        $qb = $this->createQueryBuilder('m');
        UuidQueryParameter::eq($qb, 'm.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('m.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

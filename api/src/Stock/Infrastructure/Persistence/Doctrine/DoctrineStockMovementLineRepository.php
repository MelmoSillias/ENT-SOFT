<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Repository\StockMovementLineRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<StockMovementLine> */
class DoctrineStockMovementLineRepository extends ServiceEntityRepository implements StockMovementLineRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockMovementLine::class);
    }

    public function save(StockMovementLine $line): void
    {
        $this->getEntityManager()->persist($line);
        $this->getEntityManager()->flush();
    }

    public function remove(StockMovementLine $line): void
    {
        $this->getEntityManager()->remove($line);
        $this->getEntityManager()->flush();
    }

    public function findByMovementId(Uuid $movementId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->join('l.movement', 'm');
        UuidQueryParameter::eq($qb, 'm.id', 'movementId', $movementId);

        return $qb->getQuery()->getResult();
    }
}

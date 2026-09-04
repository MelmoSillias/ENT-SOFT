<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Stock\Domain\Entity\StockMovementLine;
use App\Stock\Domain\Enum\StockMovementDirection;
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

    public function sumNetQuantitiesByEquipment(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.equipmentId AS equipmentId, m.direction AS direction, SUM(l.quantity) AS qty')
            ->join('l.movement', 'm')
            ->andWhere('m.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->groupBy('l.equipmentId', 'm.direction')
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $equipmentId = (string) $row['equipmentId'];
            $qty = (float) $row['qty'];
            $direction = $row['direction'] instanceof StockMovementDirection
                ? $row['direction']
                : StockMovementDirection::from((string) $row['direction']);
            $sign = $direction === StockMovementDirection::OUT ? -1.0 : 1.0;
            $totals[$equipmentId] = ($totals[$equipmentId] ?? 0.0) + ($sign * $qty);
        }

        return $totals;
    }
}

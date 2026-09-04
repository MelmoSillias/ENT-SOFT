<?php

namespace App\Stock\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Stock\Domain\Entity\Equipment;
use App\Stock\Domain\Repository\EquipmentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Equipment> */
class DoctrineEquipmentRepository extends ServiceEntityRepository implements EquipmentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipment::class);
    }

    public function save(Equipment $equipment): void
    {
        $this->getEntityManager()->persist($equipment);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Equipment
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('e.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('e.title LIKE :search OR e.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}

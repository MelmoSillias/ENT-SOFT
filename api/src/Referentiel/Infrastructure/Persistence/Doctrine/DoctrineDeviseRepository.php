<?php

namespace App\Referentiel\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Referentiel\Domain\Entity\Devise;
use App\Referentiel\Domain\Repository\DeviseRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Devise>
 */
class DoctrineDeviseRepository extends ServiceEntityRepository implements DeviseRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devise::class);
    }

    public function findById(Uuid $id): ?Devise
    {
        $qb = $this->createQueryBuilder('d');
        UuidQueryParameter::eq($qb, 'd.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByCode(string $code): ?Devise
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Devise $devise, bool $flush = true): void
    {
        $this->getEntityManager()->persist($devise);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

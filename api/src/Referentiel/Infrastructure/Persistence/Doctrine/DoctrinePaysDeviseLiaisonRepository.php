<?php

namespace App\Referentiel\Infrastructure\Persistence\Doctrine;

use App\Referentiel\Domain\Entity\PaysDeviseLiaison;
use App\Referentiel\Domain\Repository\PaysDeviseLiaisonRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PaysDeviseLiaison>
 */
class DoctrinePaysDeviseLiaisonRepository extends ServiceEntityRepository implements PaysDeviseLiaisonRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaysDeviseLiaison::class);
    }

    public function findById(Uuid $id): ?PaysDeviseLiaison
    {
        $qb = $this->createQueryBuilder('l');
        UuidQueryParameter::eq($qb, 'l.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByPaysId(Uuid $paysId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.devise', 'd')
            ->orderBy('l.isDefaut', 'DESC')
            ->addOrderBy('d.code', 'ASC');
        UuidQueryParameter::eq($qb, 'l.pays', 'paysId', $paysId);

        return $qb->getQuery()->getResult();
    }

    public function findByPaysAndDevise(Uuid $paysId, Uuid $deviseId): ?PaysDeviseLiaison
    {
        $qb = $this->createQueryBuilder('l');
        UuidQueryParameter::eq($qb, 'l.pays', 'paysId', $paysId);
        UuidQueryParameter::eq($qb, 'l.devise', 'deviseId', $deviseId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('l')
            ->innerJoin('l.pays', 'p')
            ->innerJoin('l.devise', 'd')
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('d.code', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function clearDefaultExcept(?Uuid $exceptId = null, bool $flush = true): void
    {
        $qb = $this->createQueryBuilder('l')
            ->update()
            ->set('l.isDefaut', ':false')
            ->where('l.isDefaut = :true')
            ->setParameter('false', false)
            ->setParameter('true', true);

        if (null !== $exceptId) {
            UuidQueryParameter::neq($qb, 'l.id', 'exceptId', $exceptId);
        }

        $qb->getQuery()->execute();

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function save(PaysDeviseLiaison $liaison, bool $flush = true): void
    {
        $this->getEntityManager()->persist($liaison);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaysDeviseLiaison $liaison, bool $flush = true): void
    {
        $this->getEntityManager()->remove($liaison);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

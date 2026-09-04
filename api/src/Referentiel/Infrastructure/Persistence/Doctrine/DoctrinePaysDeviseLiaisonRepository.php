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
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByPaysId(Uuid $paysId): array
    {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.devise', 'd')
            ->andWhere('l.pays = :paysId')
            ->orderBy('l.isDefaut', 'DESC')
            ->addOrderBy('d.code', 'ASC');
        UuidQueryParameter::bind($qb, 'paysId', $paysId);

        return $qb->getQuery()->getResult();
    }

    public function findByPaysAndDevise(Uuid $paysId, Uuid $deviseId): ?PaysDeviseLiaison
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.pays = :paysId')
            ->andWhere('l.devise = :deviseId');
        UuidQueryParameter::bind($qb, 'paysId', $paysId);
        UuidQueryParameter::bind($qb, 'deviseId', $deviseId);

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
            $qb->andWhere('l.id != :exceptId');
            UuidQueryParameter::bind($qb, 'exceptId', $exceptId);
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

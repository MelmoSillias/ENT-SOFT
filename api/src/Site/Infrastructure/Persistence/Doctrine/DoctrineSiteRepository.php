<?php

namespace App\Site\Infrastructure\Persistence\Doctrine;

use App\Site\Domain\Entity\Site;
use App\Site\Domain\Repository\SiteRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Site> */
class DoctrineSiteRepository extends ServiceEntityRepository implements SiteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    public function save(Site $site): void
    {
        $this->getEntityManager()->persist($site);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Site
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.id = :id');
        UuidQueryParameter::bind($qb, 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findByCode(string $code): ?Site
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.code = :code')
            ->setParameter('code', trim($code))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('s.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('s.title LIKE :search OR s.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.id IN (:ids)');
        UuidQueryParameter::bindList($qb, 'ids', $ids);

        return $qb->getQuery()->getResult();
    }
}

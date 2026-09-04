<?php

namespace App\Referentiel\Infrastructure\Persistence\Doctrine;

use App\Referentiel\Domain\Entity\HistoriqueTaux;
use App\Referentiel\Domain\Repository\HistoriqueTauxRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<HistoriqueTaux>
 */
class DoctrineHistoriqueTauxRepository extends ServiceEntityRepository implements HistoriqueTauxRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueTaux::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('h')
            ->orderBy('h.dateModification', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByLiaisonId(Uuid $liaisonId): array
    {
        $qb = $this->createQueryBuilder('h')
            ->andWhere('h.liaisonId = :liaisonId')
            ->orderBy('h.dateModification', 'DESC');
        UuidQueryParameter::bind($qb, 'liaisonId', $liaisonId);

        return $qb->getQuery()->getResult();
    }

    public function save(HistoriqueTaux $historiqueTaux, bool $flush = true): void
    {
        $this->getEntityManager()->persist($historiqueTaux);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

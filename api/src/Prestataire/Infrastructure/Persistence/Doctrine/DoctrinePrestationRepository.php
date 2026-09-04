<?php

namespace App\Prestataire\Infrastructure\Persistence\Doctrine;

use App\Prestataire\Domain\Entity\Prestation;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Prestation> */
class DoctrinePrestationRepository extends ServiceEntityRepository implements PrestationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestation::class);
    }

    public function save(Prestation $prestation): void
    {
        $this->getEntityManager()->persist($prestation);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Prestation
    {
        $qb = $this->createQueryBuilder('p');
        UuidQueryParameter::eq($qb, 'p.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPrestataireId(Uuid $prestataireId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.createdAt', 'DESC');
        UuidQueryParameter::eq($qb, 'p.prestataireId', 'prestataireId', $prestataireId);

        return $qb->getQuery()->getResult();
    }
}

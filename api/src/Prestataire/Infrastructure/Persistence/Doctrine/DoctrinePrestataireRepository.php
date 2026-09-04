<?php

namespace App\Prestataire\Infrastructure\Persistence\Doctrine;

use App\Prestataire\Domain\Entity\Prestataire;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Prestataire> */
class DoctrinePrestataireRepository extends ServiceEntityRepository implements PrestataireRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestataire::class);
    }

    public function save(Prestataire $prestataire): void
    {
        $this->getEntityManager()->persist($prestataire);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Prestataire
    {
        $qb = $this->createQueryBuilder('p');
        UuidQueryParameter::eq($qb, 'p.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('p.prenom LIKE :search OR p.nom LIKE :search OR p.email LIKE :search OR p.phone LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }
}

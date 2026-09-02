<?php

namespace App\Referentiel\Infrastructure\Persistence\Doctrine;

use App\Referentiel\Domain\Entity\Pays;
use App\Referentiel\Domain\Repository\PaysRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Pays>
 */
class DoctrinePaysRepository extends ServiceEntityRepository implements PaysRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pays::class);
    }

    public function findById(Uuid $id): ?Pays
    {
        return $this->find($id);
    }

    public function findByCode(string $code): ?Pays
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.devise', 'd')
            ->addSelect('d')
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(Pays $pays, bool $flush = true): void
    {
        $this->getEntityManager()->persist($pays);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

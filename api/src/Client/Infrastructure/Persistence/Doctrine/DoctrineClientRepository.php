<?php

namespace App\Client\Infrastructure\Persistence\Doctrine;

use App\Client\Domain\Entity\Client;
use App\Client\Domain\Repository\ClientRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Client> */
class DoctrineClientRepository extends ServiceEntityRepository implements ClientRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $client): void
    {
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Client
    {
        return $this->find($id);
    }

    public function findAllEnabled(?string $search = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('c.title', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('c.title LIKE :search OR c.code LIKE :search')
                ->setParameter('search', '%'.trim($search).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findAllDisabled(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', false)
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countEnabled(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

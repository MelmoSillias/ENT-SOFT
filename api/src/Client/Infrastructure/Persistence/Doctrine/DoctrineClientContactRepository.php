<?php

namespace App\Client\Infrastructure\Persistence\Doctrine;

use App\Client\Domain\Entity\ClientContact;
use App\Client\Domain\Repository\ClientContactRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ClientContact> */
class DoctrineClientContactRepository extends ServiceEntityRepository implements ClientContactRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientContact::class);
    }

    public function save(ClientContact $contact): void
    {
        $this->getEntityManager()->persist($contact);
        $this->getEntityManager()->flush();
    }

    public function remove(ClientContact $contact): void
    {
        $this->getEntityManager()->remove($contact);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?ClientContact
    {
        return $this->find($id);
    }

    public function findByClientId(Uuid $clientId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.clientId = :clientId')
            ->setParameter('clientId', $clientId, 'uuid')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

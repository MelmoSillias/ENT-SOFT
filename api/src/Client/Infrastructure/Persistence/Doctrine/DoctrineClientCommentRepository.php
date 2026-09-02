<?php

namespace App\Client\Infrastructure\Persistence\Doctrine;

use App\Client\Domain\Entity\ClientComment;
use App\Client\Domain\Repository\ClientCommentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ClientComment> */
class DoctrineClientCommentRepository extends ServiceEntityRepository implements ClientCommentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientComment::class);
    }

    public function save(ClientComment $comment): void
    {
        $this->getEntityManager()->persist($comment);
        $this->getEntityManager()->flush();
    }

    public function findByClientId(Uuid $clientId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.clientId = :clientId')
            ->setParameter('clientId', $clientId, 'uuid')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

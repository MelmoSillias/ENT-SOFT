<?php

namespace App\Upload\Infrastructure\Persistence\Doctrine;

use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use App\Upload\Domain\Entity\UploadSession;
use App\Upload\Domain\Repository\UploadSessionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<UploadSession> */
class DoctrineUploadSessionRepository extends ServiceEntityRepository implements UploadSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadSession::class);
    }

    public function save(UploadSession $session): void
    {
        $this->getEntityManager()->persist($session);
        $this->getEntityManager()->flush();
    }

    public function remove(UploadSession $session): void
    {
        $this->getEntityManager()->remove($session);
        $this->getEntityManager()->flush();
    }

    public function findOwned(string $publicId, Uuid $ownerId): ?UploadSession
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.publicId = :publicId')
            ->setParameter('publicId', $publicId);
        UuidQueryParameter::eq($qb, 's.ownerId', 'ownerId', $ownerId);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findExpired(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.expiresAt < :now')
            ->andWhere('s.status != :attached')
            ->setParameter('now', $now)
            ->setParameter('attached', UploadSession::STATUS_ATTACHED)
            ->getQuery()
            ->getResult();
    }
}

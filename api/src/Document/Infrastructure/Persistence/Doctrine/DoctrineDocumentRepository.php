<?php

namespace App\Document\Infrastructure\Persistence\Doctrine;

use App\Document\Domain\Entity\Document;
use App\Document\Domain\Enum\DocumentOwnerType;
use App\Document\Domain\Repository\DocumentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<Document> */
class DoctrineDocumentRepository extends ServiceEntityRepository implements DocumentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $document): void
    {
        $this->getEntityManager()->persist($document);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Document
    {
        return $this->find($id);
    }

    public function findByOwner(DocumentOwnerType $ownerType, Uuid $ownerId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.ownerType = :ownerType')
            ->andWhere('d.ownerId = :ownerId')
            ->andWhere('d.isEnabled = :enabled')
            ->setParameter('ownerType', $ownerType)
            ->setParameter('ownerId', $ownerId, 'uuid')
            ->setParameter('enabled', true)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

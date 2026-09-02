<?php

namespace App\Referentiel\Infrastructure\Persistence\Doctrine;

use App\Referentiel\Domain\Entity\ReferenceSequence;
use App\Referentiel\Domain\Enum\ReferenceSequenceType;
use App\Referentiel\Domain\Repository\ReferenceSequenceRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReferenceSequenceRepository implements ReferenceSequenceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getAndIncrement(ReferenceSequenceType $type): int
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            return $this->incrementLocked($type);
        }

        return $this->entityManager->wrapInTransaction(
            fn () => $this->incrementLocked($type),
        );
    }

    private function incrementLocked(ReferenceSequenceType $type): int
    {
        $sequence = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(ReferenceSequence::class, 's')
            ->where('s.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if ($sequence === null) {
            $sequence = new ReferenceSequence($type, 0);
            $this->entityManager->persist($sequence);
            $this->entityManager->flush();
            $this->entityManager->lock($sequence, LockMode::PESSIMISTIC_WRITE);
        }

        $next = $sequence->increment();
        $this->entityManager->flush();

        return $next;
    }
}

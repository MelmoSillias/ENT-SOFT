<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\InvoiceMonthlySequence;
use App\Finance\Domain\Repository\InvoiceMonthlySequenceRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineInvoiceMonthlySequenceRepository implements InvoiceMonthlySequenceRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getAndIncrement(string $yearMonth): int
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            return $this->incrementLocked($yearMonth);
        }

        return $this->entityManager->wrapInTransaction(
            fn () => $this->incrementLocked($yearMonth),
        );
    }

    private function incrementLocked(string $yearMonth): int
    {
        $sequence = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(InvoiceMonthlySequence::class, 's')
            ->where('s.yearMonth = :yearMonth')
            ->setParameter('yearMonth', $yearMonth)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        if ($sequence === null) {
            $sequence = new InvoiceMonthlySequence($yearMonth, 0);
            $this->entityManager->persist($sequence);
            $this->entityManager->flush();
            $this->entityManager->lock($sequence, LockMode::PESSIMISTIC_WRITE);
        }

        $next = $sequence->increment();
        $this->entityManager->flush();

        return $next;
    }
}

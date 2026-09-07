<?php

namespace App\Prestataire\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Prestataire\Domain\Entity\PrestationPaymentAllocation;
use App\Prestataire\Domain\Repository\PrestationPaymentAllocationRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<PrestationPaymentAllocation> */
class DoctrinePrestationPaymentAllocationRepository extends ServiceEntityRepository implements PrestationPaymentAllocationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrestationPaymentAllocation::class);
    }

    public function save(PrestationPaymentAllocation $allocation): void
    {
        $this->getEntityManager()->persist($allocation);
        $this->getEntityManager()->flush();
    }

    public function findEnabledByPrestationId(Uuid $prestationId): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('a.createdAt', 'DESC');
        UuidQueryParameter::eq($qb, 'a.prestationId', 'prestationId', $prestationId);

        return $qb->getQuery()->getResult();
    }

    public function findEnabledByTransactionId(Uuid $transactionId): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('a.createdAt', 'ASC');
        UuidQueryParameter::eq($qb, 'a.transactionId', 'transactionId', $transactionId);

        return $qb->getQuery()->getResult();
    }

    public function sumCompletedAmountByPrestationId(Uuid $prestationId): float
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COALESCE(SUM(a.amount), 0)')
            ->from(PrestationPaymentAllocation::class, 'a')
            ->innerJoin(FinancialTransaction::class, 't', 'WITH', 't.id = a.transactionId')
            ->andWhere('a.isEnabled = :enabled')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.status = :status')
            ->andWhere('t.category = :category')
            ->setParameter('enabled', true)
            ->setParameter('status', TransactionStatus::COMPLETED)
            ->setParameter('category', TransactionCategory::PRESTATION_PAYMENT);
        UuidQueryParameter::eq($qb, 'a.prestationId', 'prestationId', $prestationId);

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function hasEnabledByPrestationId(Uuid $prestationId): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(PrestationPaymentAllocation::class, 'a')
            ->innerJoin(FinancialTransaction::class, 't', 'WITH', 't.id = a.transactionId')
            ->andWhere('a.isEnabled = :enabled')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.category = :category')
            ->setParameter('enabled', true)
            ->setParameter('category', TransactionCategory::PRESTATION_PAYMENT);
        UuidQueryParameter::eq($qb, 'a.prestationId', 'prestationId', $prestationId);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function countEnabledByTransactionId(Uuid $transactionId): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isEnabled = :enabled')
            ->setParameter('enabled', true);
        UuidQueryParameter::eq($qb, 'a.transactionId', 'transactionId', $transactionId);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}

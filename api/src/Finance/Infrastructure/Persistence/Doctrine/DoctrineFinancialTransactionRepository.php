<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\SharedKernel\Infrastructure\Persistence\Doctrine\UuidQueryParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<FinancialTransaction> */
class DoctrineFinancialTransactionRepository extends ServiceEntityRepository implements FinancialTransactionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialTransaction::class);
    }

    public function save(FinancialTransaction $transaction): void
    {
        $this->getEntityManager()->persist($transaction);
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?FinancialTransaction
    {
        $qb = $this->createQueryBuilder('t');
        UuidQueryParameter::eq($qb, 't.id', 'id', $id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllEnabled(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('t.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findEnabledPaymentsByInvoiceId(Uuid $invoiceId): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.category = :category')
            ->setParameter('enabled', true)
            ->setParameter('category', TransactionCategory::INVOICE_PAYMENT)
            ->orderBy('t.date', 'DESC');
        UuidQueryParameter::eq($qb, 't.invoiceId', 'invoiceId', $invoiceId);

        return $qb->getQuery()->getResult();
    }

    public function findEnabledPaymentsByPrestationId(Uuid $prestationId): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.category = :category')
            ->setParameter('enabled', true)
            ->setParameter('category', TransactionCategory::PRESTATION_PAYMENT)
            ->orderBy('t.date', 'DESC');
        UuidQueryParameter::eq($qb, 't.prestationId', 'prestationId', $prestationId);

        return $qb->getQuery()->getResult();
    }

    public function findStatsAggregates(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.type AS type, COUNT(t.id) AS cnt, COALESCE(SUM(t.amount), 0) AS total')
            ->andWhere('t.isEnabled = :enabled')
            ->andWhere('t.status = :status')
            ->setParameter('enabled', true)
            ->setParameter('status', TransactionStatus::COMPLETED)
            ->groupBy('t.type')
            ->getQuery()
            ->getResult();

        $stats = [
            'incomeCount' => 0,
            'incomeSum' => 0.0,
            'expenseCount' => 0,
            'expenseSum' => 0.0,
        ];

        foreach ($rows as $row) {
            $type = $row['type'] instanceof TransactionType ? $row['type'] : TransactionType::from((string) $row['type']);
            $count = (int) $row['cnt'];
            $sum = (float) $row['total'];

            if ($type === TransactionType::INCOME) {
                $stats['incomeCount'] = $count;
                $stats['incomeSum'] = $sum;
            } elseif ($type === TransactionType::EXPENSE) {
                $stats['expenseCount'] = $count;
                $stats['expenseSum'] = $sum;
            }
        }

        return $stats;
    }
}

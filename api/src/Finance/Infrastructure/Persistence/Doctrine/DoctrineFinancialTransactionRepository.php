<?php

namespace App\Finance\Infrastructure\Persistence\Doctrine;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
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
        return $this->find($id);
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
}

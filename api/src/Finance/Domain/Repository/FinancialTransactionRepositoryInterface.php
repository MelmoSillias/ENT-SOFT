<?php

namespace App\Finance\Domain\Repository;

use App\Finance\Domain\Entity\FinancialTransaction;
use Symfony\Component\Uid\Uuid;

interface FinancialTransactionRepositoryInterface
{
    public function save(FinancialTransaction $transaction): void;

    public function findById(Uuid $id): ?FinancialTransaction;

    /** @return list<FinancialTransaction> */
    public function findAllEnabled(): array;
}

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

    /** @return list<FinancialTransaction> */
    public function findEnabledPaymentsByInvoiceId(Uuid $invoiceId): array;

    /** @return list<FinancialTransaction> */
    public function findEnabledPaymentsByPrestationId(Uuid $prestationId): array;

    /**
     * @return array{incomeCount: int, incomeSum: float, expenseCount: int, expenseSum: float}
     */
    public function findStatsAggregates(): array;
}

<?php

namespace App\Finance\Application\Query\ListFinancialTransactions;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\SharedKernel\Domain\Period\PeriodBounds;

final class ListFinancialTransactionsHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(?string $from = null, ?string $to = null): array
    {
        return array_map(
            static fn ($t) => FinancialTransactionResponseDto::fromEntity($t)->toArray(),
            $this->transactionRepository->findAllEnabled(
                PeriodBounds::from($from),
                PeriodBounds::to($to),
            ),
        );
    }
}

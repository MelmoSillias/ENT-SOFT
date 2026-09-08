<?php

namespace App\Finance\Application\Query\ListFinancialTransactions;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;

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
                $from ? new \DateTimeImmutable($from) : null,
                $to ? new \DateTimeImmutable($to) : null,
            ),
        );
    }
}

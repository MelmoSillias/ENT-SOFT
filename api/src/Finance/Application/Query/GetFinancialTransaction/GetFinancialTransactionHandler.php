<?php

namespace App\Finance\Application\Query\GetFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(GetFinancialTransactionQuery $query): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($query->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($query->id);
        }

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

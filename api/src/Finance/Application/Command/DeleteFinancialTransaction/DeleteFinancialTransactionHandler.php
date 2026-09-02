<?php

namespace App\Finance\Application\Command\DeleteFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(DeleteFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($command->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($command->id);
        }

        $transaction->disable();
        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

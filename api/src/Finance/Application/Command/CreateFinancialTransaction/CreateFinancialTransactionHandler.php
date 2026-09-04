<?php

namespace App\Finance\Application\Command\CreateFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class CreateFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(CreateFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $fromParty = trim($command->fromParty);
        $toParty = trim($command->toParty);

        $transaction = new FinancialTransaction(
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            type: TransactionType::from($command->type),
            category: TransactionCategory::from($command->category),
            status: TransactionStatus::from($command->status),
            fromParty: $fromParty !== '' ? $fromParty : null,
            toParty: $toParty !== '' ? $toParty : null,
            description: $command->description,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
            invoiceId: $command->invoiceId ? Uuid::fromString($command->invoiceId) : null,
            prestationId: $command->prestationId ? Uuid::fromString($command->prestationId) : null,
        );
        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

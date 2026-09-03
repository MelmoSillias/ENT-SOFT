<?php

namespace App\Finance\Application\Command\CreateFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class CreateFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(CreateFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = new FinancialTransaction(
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            type: TransactionType::from($command->type),
            category: TransactionCategory::from($command->category),
            status: TransactionStatus::from($command->status),
            fromParty: FieldValidator::requireNonEmpty($command->fromParty, 'Émetteur'),
            toParty: FieldValidator::requireNonEmpty($command->toParty, 'Destinataire'),
            description: $command->description,
            clientId: $command->clientId ? Uuid::fromString($command->clientId) : null,
            projectId: $command->projectId ? Uuid::fromString($command->projectId) : null,
            siteId: $command->siteId ? Uuid::fromString($command->siteId) : null,
            invoiceId: $command->invoiceId ? Uuid::fromString($command->invoiceId) : null,
        );
        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

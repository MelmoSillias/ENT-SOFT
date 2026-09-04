<?php

namespace App\Finance\Application\Command\UpdateFinancialTransaction;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Exception\FinancialTransactionNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateFinancialTransactionHandler
{
    public function __construct(
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
    ) {
    }

    public function handle(UpdateFinancialTransactionCommand $command): FinancialTransactionResponseDto
    {
        $transaction = $this->transactionRepository->findById(Uuid::fromString($command->id));
        if (null === $transaction || !$transaction->isEnabled()) {
            throw FinancialTransactionNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $transaction->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->amount !== null) {
            $transaction->setAmount($command->amount);
        }
        if ($command->type !== null) {
            $transaction->setType(TransactionType::from($command->type));
        }
        if ($command->category !== null) {
            $transaction->setCategory(TransactionCategory::from($command->category));
        }
        if ($command->description !== null) {
            $transaction->setDescription($command->description);
        }
        if ($command->status !== null) {
            $transaction->setStatus(TransactionStatus::from($command->status));
        }
        if ($command->fromParty !== null) {
            $trimmed = trim($command->fromParty);
            $transaction->setFromParty($trimmed !== '' ? $trimmed : null);
        }
        if ($command->toParty !== null) {
            $trimmed = trim($command->toParty);
            $transaction->setToParty($trimmed !== '' ? $trimmed : null);
        }
        if ($command->clientId !== null) {
            $transaction->setClientId($command->clientId !== '' ? Uuid::fromString($command->clientId) : null);
        }
        if ($command->siteId !== null) {
            $transaction->setSiteId($command->siteId !== '' ? Uuid::fromString($command->siteId) : null);
        }
        if ($command->invoiceId !== null) {
            $transaction->setInvoiceId($command->invoiceId !== '' ? Uuid::fromString($command->invoiceId) : null);
        }
        if ($command->prestationId !== null) {
            $transaction->setPrestationId($command->prestationId !== '' ? Uuid::fromString($command->prestationId) : null);
        }

        $this->transactionRepository->save($transaction);

        return FinancialTransactionResponseDto::fromEntity($transaction);
    }
}

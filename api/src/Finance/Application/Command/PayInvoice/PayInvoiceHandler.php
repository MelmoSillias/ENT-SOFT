<?php

namespace App\Finance\Application\Command\PayInvoice;

use App\Client\Domain\Repository\ClientRepositoryInterface;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class PayInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly SettingRepositoryInterface $settingRepository,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(PayInvoiceCommand $command): InvoiceResponseDto
    {
        if ($command->amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit être supérieur à 0.');
        }

        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        $client = $this->clientRepository->findById($invoice->getClientId());
        $fromParty = $client?->getTitle() ?? 'Client';
        $toParty = $this->settingRepository->findByCle('AGENCE_NOM')?->getValeur() ?: 'ENT';

        $transaction = new FinancialTransaction(
            date: new \DateTimeImmutable($command->date),
            amount: $command->amount,
            type: TransactionType::INCOME,
            category: TransactionCategory::INVOICE_PAYMENT,
            status: TransactionStatus::COMPLETED,
            fromParty: $fromParty,
            toParty: $toParty,
            description: $command->description,
            clientId: $invoice->getClientId(),
            siteId: null,
            invoiceId: $invoice->getId(),
        );
        $this->transactionRepository->save($transaction);

        $invoice->setStatus(InvoiceStatus::INVOICED);
        $this->invoiceRepository->save($invoice);

        return $this->assembler->toDto($invoice);
    }
}

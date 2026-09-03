<?php

namespace App\Finance\Application\Command\ResetInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ResetInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(ResetInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        foreach ($this->transactionRepository->findEnabledPaymentsByInvoiceId($invoice->getId()) as $payment) {
            $payment->setStatus(TransactionStatus::CANCELLED);
            $payment->disable();
            $this->transactionRepository->save($payment);
        }

        $invoice->setStatus(InvoiceStatus::DRAFT);
        $this->invoiceRepository->save($invoice);

        return $this->assembler->toDto($invoice);
    }
}

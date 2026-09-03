<?php

namespace App\Finance\Application\Command\DeleteInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceCannotBeModifiedException;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(DeleteInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        $payments = $this->transactionRepository->findEnabledPaymentsByInvoiceId($invoice->getId());
        if (\count($payments) > 0 || $invoice->getStatus() !== InvoiceStatus::DRAFT) {
            throw InvoiceCannotBeModifiedException::cannotDelete();
        }

        $invoice->disable();
        $this->invoiceRepository->save($invoice);

        return $this->assembler->toDto($invoice);
    }
}

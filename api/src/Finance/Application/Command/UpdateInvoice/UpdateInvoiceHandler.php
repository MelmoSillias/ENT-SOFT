<?php

namespace App\Finance\Application\Command\UpdateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Application\Service\InvoiceLineWriter;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceCannotBeModifiedException;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly InvoiceLineWriter $lineWriter,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    public function handle(UpdateInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        $payments = $this->transactionRepository->findEnabledPaymentsByInvoiceId($invoice->getId());
        if (\count($payments) > 0) {
            throw InvoiceCannotBeModifiedException::hasPayments();
        }

        if ($command->date !== null) {
            $invoice->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->status !== null) {
            $invoice->setStatus(InvoiceStatus::from($command->status));
        }
        if ($command->clientId !== null) {
            $invoice->setClientId(Uuid::fromString($command->clientId));
        }
        if ($command->updateProject) {
            $projectId = $command->projectId !== null && $command->projectId !== ''
                ? Uuid::fromString($command->projectId)
                : null;
            $projectLabel = $command->projectLabel !== null ? trim($command->projectLabel) : null;
            if ($projectLabel === '') {
                $projectLabel = null;
            }
            $invoice->setProjectId($projectId);
            $invoice->setProjectLabel($projectId ? null : $projectLabel);
        }

        $this->invoiceRepository->save($invoice);

        if ($command->lines !== null) {
            $this->lineWriter->replaceLines($invoice, $command->lines);
        }

        return $this->assembler->toDto($invoice);
    }
}

<?php

namespace App\Finance\Application\Command\UpdateInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class UpdateInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(UpdateInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        if ($command->date !== null) {
            $invoice->setDate(new \DateTimeImmutable($command->date));
        }
        if ($command->amount !== null) {
            $invoice->setAmount($command->amount);
        }
        if ($command->status !== null) {
            $invoice->setStatus(InvoiceStatus::from($command->status));
        }
        if ($command->clientId !== null) {
            $invoice->setClientId(Uuid::fromString($command->clientId));
        }
        if ($command->projectId !== null) {
            $invoice->setProjectId($command->projectId !== '' ? Uuid::fromString($command->projectId) : null);
        }

        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

<?php

namespace App\Finance\Application\Command\DeleteInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(DeleteInvoiceCommand $command): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($command->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($command->id);
        }

        $invoice->disable();
        $this->invoiceRepository->save($invoice);

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

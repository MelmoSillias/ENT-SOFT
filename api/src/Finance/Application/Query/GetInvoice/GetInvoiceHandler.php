<?php

namespace App\Finance\Application\Query\GetInvoice;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Exception\InvoiceNotFoundException;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetInvoiceHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function handle(GetInvoiceQuery $query): InvoiceResponseDto
    {
        $invoice = $this->invoiceRepository->findById(Uuid::fromString($query->id));
        if (null === $invoice || !$invoice->isEnabled()) {
            throw InvoiceNotFoundException::withId($query->id);
        }

        return InvoiceResponseDto::fromEntity($invoice);
    }
}

<?php

namespace App\Finance\Application\Query\ListInvoices;

use App\Finance\Application\Service\InvoiceAssembler;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;

final class ListInvoicesHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly InvoiceAssembler $assembler,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            fn ($invoice) => $this->assembler->toDto($invoice)->toArray(),
            $this->invoiceRepository->findAllEnabled(),
        );
    }
}

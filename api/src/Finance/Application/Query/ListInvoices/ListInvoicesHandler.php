<?php

namespace App\Finance\Application\Query\ListInvoices;

use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;

final class ListInvoicesHandler
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return array_map(
            static fn ($i) => InvoiceResponseDto::fromEntity($i)->toArray(),
            $this->invoiceRepository->findAllEnabled(),
        );
    }
}

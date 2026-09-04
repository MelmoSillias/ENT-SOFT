<?php

namespace App\Finance\Application\Service;

use App\Finance\Application\Dto\FinancialTransactionResponseDto;
use App\Finance\Application\Dto\InvoiceLineResponseDto;
use App\Finance\Application\Dto\InvoiceResponseDto;
use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;

final class InvoiceAssembler
{
    public function __construct(
        private readonly InvoiceLineRepositoryInterface $lineRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly InvoiceNumberResolver $numberResolver,
    ) {
    }

    public function toDto(Invoice $invoice): InvoiceResponseDto
    {
        $lines = array_map(
            static fn ($line) => InvoiceLineResponseDto::fromEntity($line)->toArray(),
            $this->lineRepository->findByInvoiceId($invoice->getId()),
        );
        $payments = array_map(
            static fn ($payment) => FinancialTransactionResponseDto::fromEntity($payment)->toArray(),
            $this->transactionRepository->findEnabledPaymentsByInvoiceId($invoice->getId()),
        );

        return InvoiceResponseDto::fromEntity(
            $invoice,
            $this->numberResolver->resolve($invoice),
            $lines,
            $payments,
        );
    }
}

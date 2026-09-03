<?php

namespace App\Finance\Application\Service;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Entity\InvoiceLine;
use App\Finance\Domain\Repository\InvoiceLineRepositoryInterface;
use App\Finance\Domain\Repository\InvoiceRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class InvoiceLineWriter
{
    public function __construct(
        private readonly InvoiceLineRepositoryInterface $lineRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    public function replaceLines(Invoice $invoice, array $lines): float
    {
        foreach ($this->lineRepository->findByInvoiceId($invoice->getId()) as $existing) {
            $this->lineRepository->remove($existing);
        }

        $total = 0.0;
        foreach ($lines as $lineData) {
            $description = FieldValidator::requireNonEmpty((string) ($lineData['description'] ?? ''), 'Libellé de ligne');
            $quantity = (float) ($lineData['quantity'] ?? 0);
            $unitPrice = (float) ($lineData['unitPrice'] ?? 0);
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('La quantité de ligne doit être supérieure à 0.');
            }
            $line = new InvoiceLine($invoice, $description, $quantity, $unitPrice);
            $this->lineRepository->save($line);
            $total += $line->getAmount();
        }

        $invoice->setAmount($total);
        $this->invoiceRepository->save($invoice);

        return $total;
    }
}

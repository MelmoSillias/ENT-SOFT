<?php

namespace App\Finance\Domain\Repository;

use App\Finance\Domain\Entity\InvoiceLine;
use Symfony\Component\Uid\Uuid;

interface InvoiceLineRepositoryInterface
{
    public function save(InvoiceLine $line): void;

    public function remove(InvoiceLine $line): void;

    /** @return list<InvoiceLine> */
    public function findByInvoiceId(Uuid $invoiceId): array;
}

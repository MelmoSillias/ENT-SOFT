<?php

namespace App\Finance\Domain\Repository;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use Symfony\Component\Uid\Uuid;

interface InvoiceRepositoryInterface
{
    public function save(Invoice $invoice): void;

    public function findById(Uuid $id): ?Invoice;

    /** @return list<Invoice> */
    public function findAllEnabled(): array;

    public function countByClientId(Uuid $clientId): int;

    public function countByStatus(InvoiceStatus $status): int;
}

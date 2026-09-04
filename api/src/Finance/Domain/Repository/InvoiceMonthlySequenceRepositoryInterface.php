<?php

namespace App\Finance\Domain\Repository;

interface InvoiceMonthlySequenceRepositoryInterface
{
    public function getAndIncrement(string $yearMonth): int;
}

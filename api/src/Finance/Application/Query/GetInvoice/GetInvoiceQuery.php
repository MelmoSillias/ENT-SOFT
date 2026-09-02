<?php

namespace App\Finance\Application\Query\GetInvoice;

final readonly class GetInvoiceQuery
{
    public function __construct(public string $id) {}
}

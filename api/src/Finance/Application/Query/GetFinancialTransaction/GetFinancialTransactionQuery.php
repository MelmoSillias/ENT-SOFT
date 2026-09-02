<?php

namespace App\Finance\Application\Query\GetFinancialTransaction;

final readonly class GetFinancialTransactionQuery
{
    public function __construct(public string $id) {}
}

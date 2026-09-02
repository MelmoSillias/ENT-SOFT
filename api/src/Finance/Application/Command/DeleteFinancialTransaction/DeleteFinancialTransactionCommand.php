<?php

namespace App\Finance\Application\Command\DeleteFinancialTransaction;

final readonly class DeleteFinancialTransactionCommand
{
    public function __construct(public string $id) {}
}

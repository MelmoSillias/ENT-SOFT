<?php

namespace App\Finance\Application\Command\ResetInvoice;

final readonly class ResetInvoiceCommand
{
    public function __construct(public string $id)
    {
    }
}

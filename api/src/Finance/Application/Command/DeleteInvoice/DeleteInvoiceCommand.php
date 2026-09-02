<?php

namespace App\Finance\Application\Command\DeleteInvoice;

final readonly class DeleteInvoiceCommand
{
    public function __construct(public string $id) {}
}

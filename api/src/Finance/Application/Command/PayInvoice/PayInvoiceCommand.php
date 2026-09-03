<?php

namespace App\Finance\Application\Command\PayInvoice;

final readonly class PayInvoiceCommand
{
    public function __construct(
        public string $id,
        public string $date,
        public float $amount,
        public ?string $description = null,
    ) {
    }
}

<?php

namespace App\Finance\Application\Command\CreateInvoice;

final readonly class CreateInvoiceCommand
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $clientId,
        public string $status = 'draft',
        public ?string $projectId = null,
    ) {
    }
}

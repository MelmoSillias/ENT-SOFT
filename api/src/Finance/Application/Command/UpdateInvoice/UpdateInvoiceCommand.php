<?php

namespace App\Finance\Application\Command\UpdateInvoice;

final readonly class UpdateInvoiceCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $amount = null,
        public ?string $status = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
    ) {
    }
}

<?php

namespace App\Finance\Application\Command\CreateFinancialTransaction;

final readonly class CreateFinancialTransactionCommand
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $type,
        public string $category,
        public string $status,
        public string $fromParty = '',
        public string $toParty = '',
        public ?string $description = null,
        public ?string $clientId = null,
        public ?string $siteId = null,
        public ?string $invoiceId = null,
        public ?string $prestationId = null,
    ) {
    }
}

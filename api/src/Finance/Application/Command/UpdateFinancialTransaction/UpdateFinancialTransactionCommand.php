<?php

namespace App\Finance\Application\Command\UpdateFinancialTransaction;

final readonly class UpdateFinancialTransactionCommand
{
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?float $amount = null,
        public ?string $type = null,
        public ?string $category = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $fromParty = null,
        public ?string $toParty = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?string $siteId = null,
    ) {
    }
}

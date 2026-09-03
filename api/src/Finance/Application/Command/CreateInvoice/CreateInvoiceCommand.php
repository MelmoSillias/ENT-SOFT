<?php

namespace App\Finance\Application\Command\CreateInvoice;

final readonly class CreateInvoiceCommand
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(
        public string $date,
        public string $clientId,
        public string $status = 'draft',
        public ?string $projectId = null,
        public array $lines = [],
    ) {
    }
}

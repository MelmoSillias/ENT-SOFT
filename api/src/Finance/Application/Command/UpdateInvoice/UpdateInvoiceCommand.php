<?php

namespace App\Finance\Application\Command\UpdateInvoice;

final readonly class UpdateInvoiceCommand
{
    /** @param list<array<string, mixed>>|null $lines */
    public function __construct(
        public string $id,
        public ?string $date = null,
        public ?string $status = null,
        public ?string $clientId = null,
        public ?string $projectId = null,
        public ?array $lines = null,
    ) {
    }
}

<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\Invoice;

final readonly class InvoiceResponseDto
{
    public function __construct(
        public string $id,
        public string $number,
        public string $date,
        public float $amount,
        public string $status,
        public string $clientId,
        public ?string $projectId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Invoice $invoice): self
    {
        return new self(
            id: (string) $invoice->getId(),
            number: $invoice->getNumber(),
            date: $invoice->getDate()->format('Y-m-d'),
            amount: $invoice->getAmount(),
            status: $invoice->getStatus()->value,
            clientId: (string) $invoice->getClientId(),
            projectId: $invoice->getProjectId()?->toRfc4122(),
            isEnabled: $invoice->isEnabled(),
            createdAt: $invoice->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $invoice->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date,
            'amount' => $this->amount,
            'status' => $this->status,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

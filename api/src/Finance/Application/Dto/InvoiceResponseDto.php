<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\Invoice;

final readonly class InvoiceResponseDto
{
    /**
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $payments
     */
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
        public array $lines = [],
        public array $payments = [],
        public float $paidAmount = 0.0,
        public bool $hasPayments = false,
    ) {
    }

    public static function fromEntity(Invoice $invoice, array $lines = [], array $payments = []): self
    {
        $paidAmount = 0.0;
        foreach ($payments as $payment) {
            $paidAmount += (float) ($payment['amount'] ?? 0);
        }

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
            lines: $lines,
            payments: $payments,
            paidAmount: $paidAmount,
            hasPayments: $paidAmount > 0 || \count($payments) > 0,
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
            'lines' => $this->lines,
            'payments' => $this->payments,
            'paidAmount' => $this->paidAmount,
            'hasPayments' => $this->hasPayments,
        ];
    }
}

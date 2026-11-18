<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\Invoice;

final readonly class InvoiceResponseDto
{
    /**
     * @param list<array<string, mixed>> $lines
     * @param list<array<string, mixed>> $payments
     * @param list<string> $paymentTerms
     */
    public function __construct(
        public string $id,
        public string $number,
        public string $numberSequential,
        public string $numberMonthly,
        public string $date,
        public float $amount,
        public string $status,
        public string $clientId,
        public ?string $projectId,
        public ?string $projectLabel,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
        public array $lines = [],
        public array $payments = [],
        public float $paidAmount = 0.0,
        public bool $hasPayments = false,
        public bool $vatEnabled = false,
        public float $vatRate = 18.0,
        public float $subtotal = 0.0,
        public float $vatAmount = 0.0,
        public array $paymentTerms = [],
    ) {
    }

    public static function fromEntity(
        Invoice $invoice,
        string $displayNumber,
        array $lines = [],
        array $payments = [],
    ): self {
        $paidAmount = 0.0;
        foreach ($payments as $payment) {
            $paidAmount += (float) ($payment['amount'] ?? 0);
        }

        return new self(
            id: (string) $invoice->getId(),
            number: $displayNumber,
            numberSequential: $invoice->getNumber(),
            numberMonthly: $invoice->getNumberMonthly(),
            date: $invoice->getDate()->format('Y-m-d'),
            amount: $invoice->getAmount(),
            status: $invoice->getStatus()->value,
            clientId: (string) $invoice->getClientId(),
            projectId: $invoice->getProjectId()?->toRfc4122(),
            projectLabel: $invoice->getProjectLabel(),
            isEnabled: $invoice->isEnabled(),
            createdAt: $invoice->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $invoice->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            lines: $lines,
            payments: $payments,
            paidAmount: $paidAmount,
            hasPayments: $paidAmount > 0 || \count($payments) > 0,
            vatEnabled: $invoice->isVatEnabled(),
            vatRate: $invoice->getVatRate(),
            subtotal: $invoice->getSubtotal(),
            vatAmount: $invoice->getVatAmount(),
            paymentTerms: $invoice->getPaymentTerms(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'numberSequential' => $this->numberSequential,
            'numberMonthly' => $this->numberMonthly,
            'date' => $this->date,
            'amount' => $this->amount,
            'total' => $this->amount,
            'vatEnabled' => $this->vatEnabled,
            'vatRate' => $this->vatRate,
            'subtotal' => $this->subtotal,
            'vatAmount' => $this->vatAmount,
            'paymentTerms' => $this->paymentTerms,
            'status' => $this->status,
            'clientId' => $this->clientId,
            'projectId' => $this->projectId,
            'projectLabel' => $this->projectLabel,
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

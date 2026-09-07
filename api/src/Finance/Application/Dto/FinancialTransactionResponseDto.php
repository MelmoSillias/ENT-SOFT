<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;

final readonly class FinancialTransactionResponseDto
{
    public function __construct(
        public string $id,
        public string $date,
        public float $amount,
        public string $type,
        public string $category,
        public ?string $description,
        public string $status,
        public ?string $fromParty,
        public ?string $toParty,
        public ?string $clientId,
        public ?string $siteId,
        public ?string $invoiceId,
        public ?string $prestationId,
        public bool $isSystemGenerated,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(FinancialTransaction $t): self
    {
        return new self(
            id: (string) $t->getId(),
            date: $t->getDate()->format('Y-m-d'),
            amount: $t->getAmount(),
            type: $t->getType()->value,
            category: $t->getCategory()->value,
            description: $t->getDescription(),
            status: $t->getStatus()->value,
            fromParty: $t->getFromParty(),
            toParty: $t->getToParty(),
            clientId: $t->getClientId()?->toRfc4122(),
            siteId: $t->getSiteId()?->toRfc4122(),
            invoiceId: $t->getInvoiceId()?->toRfc4122(),
            prestationId: $t->getPrestationId()?->toRfc4122(),
            isSystemGenerated: self::isSystemGeneratedCategory($t->getCategory()),
            isEnabled: $t->isEnabled(),
            createdAt: $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    public static function isSystemGeneratedCategory(TransactionCategory $category): bool
    {
        return in_array($category, [
            TransactionCategory::PRESTATION_PAYMENT,
            TransactionCategory::INVOICE_PAYMENT,
        ], true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'amount' => $this->amount,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'status' => $this->status,
            'fromParty' => $this->fromParty,
            'toParty' => $this->toParty,
            'clientId' => $this->clientId,
            'siteId' => $this->siteId,
            'invoiceId' => $this->invoiceId,
            'prestationId' => $this->prestationId,
            'isSystemGenerated' => $this->isSystemGenerated,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

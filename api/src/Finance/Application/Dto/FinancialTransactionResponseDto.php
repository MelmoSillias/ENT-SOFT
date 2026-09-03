<?php

namespace App\Finance\Application\Dto;

use App\Finance\Domain\Entity\FinancialTransaction;

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
        public string $fromParty,
        public string $toParty,
        public ?string $clientId,
        public ?string $projectId,
        public ?string $siteId,
        public ?string $invoiceId,
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
            projectId: $t->getProjectId()?->toRfc4122(),
            siteId: $t->getSiteId()?->toRfc4122(),
            invoiceId: $t->getInvoiceId()?->toRfc4122(),
            isEnabled: $t->isEnabled(),
            createdAt: $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
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
            'projectId' => $this->projectId,
            'siteId' => $this->siteId,
            'invoiceId' => $this->invoiceId,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

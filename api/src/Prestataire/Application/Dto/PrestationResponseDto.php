<?php

namespace App\Prestataire\Application\Dto;

use App\Prestataire\Domain\Entity\Prestation;

final readonly class PrestationResponseDto
{
    public function __construct(
        public string $id,
        public string $prestataireId,
        public string $description,
        public ?string $siteId,
        public float $amount,
        public string $workStatus,
        public string $paymentStatus,
        public float $paidAmount,
        public float $remainingAmount,
        public bool $hasPayments,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
        public ?string $prestataireName = null,
    ) {
    }

    public static function fromEntity(
        Prestation $prestation,
        float $paidAmount = 0.0,
        bool $hasPayments = false,
        ?string $prestataireName = null,
    ): self {
        $amount = $prestation->getAmount();
        $remaining = max(0.0, $amount - $paidAmount);

        return new self(
            id: (string) $prestation->getId(),
            prestataireId: $prestation->getPrestataireId()->toRfc4122(),
            description: $prestation->getDescription(),
            siteId: $prestation->getSiteId()?->toRfc4122(),
            amount: $amount,
            workStatus: $prestation->getWorkStatus()->value,
            paymentStatus: $prestation->getPaymentStatus()->value,
            paidAmount: $paidAmount,
            remainingAmount: $remaining,
            hasPayments: $hasPayments,
            isEnabled: $prestation->isEnabled(),
            createdAt: $prestation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $prestation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            prestataireName: $prestataireName,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'prestataireId' => $this->prestataireId,
            'prestataireName' => $this->prestataireName,
            'description' => $this->description,
            'siteId' => $this->siteId,
            'amount' => $this->amount,
            'workStatus' => $this->workStatus,
            'paymentStatus' => $this->paymentStatus,
            'paidAmount' => $this->paidAmount,
            'remainingAmount' => $this->remainingAmount,
            'hasPayments' => $this->hasPayments,
            'isEnabled' => $this->isEnabled,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

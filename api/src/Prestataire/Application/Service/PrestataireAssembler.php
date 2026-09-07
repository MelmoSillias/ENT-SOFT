<?php

namespace App\Prestataire\Application\Service;

use App\Prestataire\Application\Dto\PrestataireResponseDto;
use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Domain\Entity\Prestataire;
use App\Prestataire\Domain\Entity\Prestation;
use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Domain\Repository\PrestationPaymentAllocationRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;

final class PrestataireAssembler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestationPaymentAllocationRepositoryInterface $allocationRepository,
    ) {
    }

    public function toPrestataireDto(Prestataire $prestataire): PrestataireResponseDto
    {
        $prestations = $this->prestationRepository->findByPrestataireId($prestataire->getId());
        $openCount = 0;
        $unpaidCompletedReliquat = 0.0;

        foreach ($prestations as $prestation) {
            if ($prestation->getWorkStatus() !== PrestationWorkStatus::COMPLETED) {
                ++$openCount;
            }

            if (
                $prestation->getWorkStatus() === PrestationWorkStatus::COMPLETED
                && in_array($prestation->getPaymentStatus(), [
                    PrestationPaymentStatus::UNPAID,
                    PrestationPaymentStatus::PARTIAL,
                ], true)
            ) {
                $paid = $this->sumPaidAmount($prestation);
                $unpaidCompletedReliquat += max(0.0, $prestation->getAmount() - $paid);
            }
        }

        return PrestataireResponseDto::fromEntity($prestataire, $openCount, $unpaidCompletedReliquat);
    }

    public function toPrestationDto(Prestation $prestation): PrestationResponseDto
    {
        $paidAmount = $this->sumPaidAmount($prestation);
        $hasPayments = $this->allocationRepository->hasEnabledByPrestationId($prestation->getId());

        return PrestationResponseDto::fromEntity($prestation, $paidAmount, $hasPayments);
    }

    public function recalculatePaymentStatus(Prestation $prestation): void
    {
        $paidAmount = $this->sumPaidAmount($prestation);

        if ($paidAmount <= 0) {
            $prestation->setPaymentStatus(PrestationPaymentStatus::UNPAID);
        } elseif ($paidAmount >= $prestation->getAmount()) {
            $prestation->setPaymentStatus(PrestationPaymentStatus::PAID);
        } else {
            $prestation->setPaymentStatus(PrestationPaymentStatus::PARTIAL);
        }
    }

    public function sumPaidAmount(Prestation $prestation): float
    {
        return $this->allocationRepository->sumCompletedAmountByPrestationId($prestation->getId());
    }
}

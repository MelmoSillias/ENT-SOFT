<?php

namespace App\Prestataire\Application\Command\ResetPrestationPayments;

use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Application\Service\PrestationPaymentDescription;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationPaymentAllocationRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ResetPrestationPaymentsHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly PrestationPaymentAllocationRepositoryInterface $allocationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(ResetPrestationPaymentsCommand $command): PrestationResponseDto
    {
        $prestation = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        $prestataire = $this->prestataireRepository->findById($prestation->getPrestataireId());
        $prestataireName = $prestataire?->getFullName() ?? 'Prestataire';

        $allocations = $this->allocationRepository->findEnabledByPrestationId($prestation->getId());

        foreach ($allocations as $allocation) {
            $transaction = $this->transactionRepository->findById($allocation->getTransactionId());
            if (null === $transaction || !$transaction->isEnabled()) {
                $allocation->disable();
                $this->allocationRepository->save($allocation);
                continue;
            }

            $allocation->disable();
            $this->allocationRepository->save($allocation);

            $remainingCount = $this->allocationRepository->countEnabledByTransactionId($transaction->getId());
            $newAmount = max(0.0, $transaction->getAmount() - $allocation->getAmount());
            $transaction->setAmount($newAmount);

            if ($remainingCount <= 0 || $newAmount <= 0) {
                $transaction->setStatus(TransactionStatus::CANCELLED);
                $transaction->disable();
            } else {
                $transaction->setDescription(
                    PrestationPaymentDescription::build($prestataireName, $remainingCount),
                );
                $remainingAllocations = $this->allocationRepository->findEnabledByTransactionId($transaction->getId());
                $transaction->setPrestationId(
                    $remainingAllocations !== [] ? $remainingAllocations[0]->getPrestationId() : null,
                );
            }

            $this->transactionRepository->save($transaction);
        }

        $this->assembler->recalculatePaymentStatus($prestation);
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

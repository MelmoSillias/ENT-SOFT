<?php

namespace App\Prestataire\Application\Command\PayPrestation;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Application\Service\PrestationPaymentDescription;
use App\Prestataire\Domain\Entity\PrestationPaymentAllocation;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationPaymentAllocationRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class PayPrestationHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly PrestationPaymentAllocationRepositoryInterface $allocationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(PayPrestationCommand $command): PrestationResponseDto
    {
        if ($command->amount <= 0) {
            throw new \InvalidArgumentException('Le montant du paiement doit être supérieur à 0.');
        }

        $prestation = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        $prestataire = $this->prestataireRepository->findById($prestation->getPrestataireId());
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        $date = $command->date !== null && trim($command->date) !== ''
            ? new \DateTimeImmutable($command->date)
            : new \DateTimeImmutable('today');

        $description = ($command->description !== null && trim($command->description) !== '')
            ? trim($command->description)
            : PrestationPaymentDescription::build($prestataire->getFullName(), 1);

        $transaction = new FinancialTransaction(
            date: $date,
            amount: $command->amount,
            type: TransactionType::EXPENSE,
            category: TransactionCategory::PRESTATION_PAYMENT->value,
            status: TransactionStatus::COMPLETED,
            fromParty: null,
            toParty: null,
            description: $description,
            clientId: null,
            siteId: $prestation->getSiteId(),
            invoiceId: null,
            prestationId: $prestation->getId(),
        );
        $this->transactionRepository->save($transaction);

        $allocation = new PrestationPaymentAllocation(
            $transaction->getId(),
            $prestation->getId(),
            $command->amount,
        );
        $this->allocationRepository->save($allocation);

        $this->assembler->recalculatePaymentStatus($prestation);
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

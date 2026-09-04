<?php

namespace App\Prestataire\Application\Command\PayPrestation;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class PayPrestationHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
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

        $date = $command->date !== null && trim($command->date) !== ''
            ? new \DateTimeImmutable($command->date)
            : new \DateTimeImmutable('today');

        $transaction = new FinancialTransaction(
            date: $date,
            amount: $command->amount,
            type: TransactionType::EXPENSE,
            category: TransactionCategory::PRESTATION_PAYMENT,
            status: TransactionStatus::COMPLETED,
            fromParty: null,
            toParty: null,
            description: $command->description,
            clientId: null,
            siteId: $prestation->getSiteId(),
            invoiceId: null,
            prestationId: $prestation->getId(),
        );
        $this->transactionRepository->save($transaction);

        $this->assembler->recalculatePaymentStatus($prestation);
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

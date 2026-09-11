<?php

namespace App\Prestataire\Application\Command\PayPrestations;

use App\Finance\Domain\Entity\FinancialTransaction;
use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Application\Service\PrestationPaymentDescription;
use App\Prestataire\Domain\Entity\Prestation;
use App\Prestataire\Domain\Entity\PrestationPaymentAllocation;
use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Exception\PrestataireNotFoundException;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestataireRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationPaymentAllocationRepositoryInterface;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class PayPrestationsHandler
{
    public function __construct(
        private readonly PrestataireRepositoryInterface $prestataireRepository,
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly PrestationPaymentAllocationRepositoryInterface $allocationRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(PayPrestationsCommand $command): array
    {
        if ($command->allocations === []) {
            throw new \InvalidArgumentException('Au moins une allocation de paiement est requise.');
        }

        $prestataire = $this->prestataireRepository->findById(Uuid::fromString($command->prestataireId));
        if (null === $prestataire || !$prestataire->isEnabled()) {
            throw PrestataireNotFoundException::withId($command->prestataireId);
        }

        $date = $command->date !== null && trim($command->date) !== ''
            ? new \DateTimeImmutable($command->date)
            : new \DateTimeImmutable('today');

        /** @var list<array{prestation: Prestation, amount: float}> $resolved */
        $resolved = [];
        $total = 0.0;
        $siteId = null;

        foreach ($command->allocations as $line) {
            $prestationId = (string) ($line['prestationId'] ?? '');
            $amount = (float) ($line['amount'] ?? 0);
            if ($prestationId === '' || $amount <= 0) {
                throw new \InvalidArgumentException('Chaque allocation doit avoir un montant supérieur à 0.');
            }

            $prestation = $this->prestationRepository->findById(Uuid::fromString($prestationId));
            if (null === $prestation || !$prestation->isEnabled()) {
                throw PrestationNotFoundException::withId($prestationId);
            }
            if (!$prestation->getPrestataireId()->equals($prestataire->getId())) {
                throw new \InvalidArgumentException('Toutes les prestations doivent appartenir au même prestataire.');
            }
            if ($prestation->getPaymentStatus() === PrestationPaymentStatus::PAID) {
                throw new \InvalidArgumentException('Impossible de payer une prestation déjà soldée.');
            }

            if ($siteId === null && $prestation->getSiteId() !== null) {
                $siteId = $prestation->getSiteId();
            }

            $resolved[] = ['prestation' => $prestation, 'amount' => $amount];
            $total += $amount;
        }

        if ($total <= 0) {
            throw new \InvalidArgumentException('Le montant total du paiement doit être supérieur à 0.');
        }

        $count = count($resolved);
        $firstPrestationId = $count === 1 ? $resolved[0]['prestation']->getId() : null;

        $transaction = new FinancialTransaction(
            date: $date,
            amount: $total,
            type: TransactionType::EXPENSE,
            category: TransactionCategory::PRESTATION_PAYMENT->value,
            status: TransactionStatus::COMPLETED,
            fromParty: null,
            toParty: null,
            description: PrestationPaymentDescription::build($prestataire->getFullName(), $count),
            clientId: null,
            siteId: $siteId,
            invoiceId: null,
            prestationId: $firstPrestationId,
        );
        $this->transactionRepository->save($transaction);

        $result = [];
        foreach ($resolved as $item) {
            $allocation = new PrestationPaymentAllocation(
                $transaction->getId(),
                $item['prestation']->getId(),
                $item['amount'],
            );
            $this->allocationRepository->save($allocation);

            $this->assembler->recalculatePaymentStatus($item['prestation']);
            $this->prestationRepository->save($item['prestation']);
            $result[] = $this->assembler->toPrestationDto($item['prestation'])->toArray();
        }

        return $result;
    }
}

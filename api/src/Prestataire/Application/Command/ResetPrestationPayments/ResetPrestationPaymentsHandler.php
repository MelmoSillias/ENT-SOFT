<?php

namespace App\Prestataire\Application\Command\ResetPrestationPayments;

use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Repository\FinancialTransactionRepositoryInterface;
use App\Prestataire\Application\Dto\PrestationResponseDto;
use App\Prestataire\Application\Service\PrestataireAssembler;
use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Exception\PrestationNotFoundException;
use App\Prestataire\Domain\Repository\PrestationRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class ResetPrestationPaymentsHandler
{
    public function __construct(
        private readonly PrestationRepositoryInterface $prestationRepository,
        private readonly FinancialTransactionRepositoryInterface $transactionRepository,
        private readonly PrestataireAssembler $assembler,
    ) {
    }

    public function handle(ResetPrestationPaymentsCommand $command): PrestationResponseDto
    {
        $prestation = $this->prestationRepository->findById(Uuid::fromString($command->id));
        if (null === $prestation || !$prestation->isEnabled()) {
            throw PrestationNotFoundException::withId($command->id);
        }

        foreach ($this->transactionRepository->findEnabledPaymentsByPrestationId($prestation->getId()) as $payment) {
            $payment->setStatus(TransactionStatus::CANCELLED);
            $payment->disable();
            $this->transactionRepository->save($payment);
        }

        $prestation->setPaymentStatus(PrestationPaymentStatus::UNPAID);
        $this->prestationRepository->save($prestation);

        return $this->assembler->toPrestationDto($prestation);
    }
}

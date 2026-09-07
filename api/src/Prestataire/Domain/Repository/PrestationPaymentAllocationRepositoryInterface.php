<?php

namespace App\Prestataire\Domain\Repository;

use App\Prestataire\Domain\Entity\PrestationPaymentAllocation;
use Symfony\Component\Uid\Uuid;

interface PrestationPaymentAllocationRepositoryInterface
{
    public function save(PrestationPaymentAllocation $allocation): void;

    /** @return list<PrestationPaymentAllocation> */
    public function findEnabledByPrestationId(Uuid $prestationId): array;

    /** @return list<PrestationPaymentAllocation> */
    public function findEnabledByTransactionId(Uuid $transactionId): array;

    public function sumCompletedAmountByPrestationId(Uuid $prestationId): float;

    public function hasEnabledByPrestationId(Uuid $prestationId): bool;

    public function countEnabledByTransactionId(Uuid $transactionId): int;
}

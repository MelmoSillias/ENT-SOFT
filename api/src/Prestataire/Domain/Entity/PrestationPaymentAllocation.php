<?php

namespace App\Prestataire\Domain\Entity;

use App\Prestataire\Infrastructure\Persistence\Doctrine\DoctrinePrestationPaymentAllocationRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrinePrestationPaymentAllocationRepository::class)]
#[ORM\Table(name: 'prestation_payment_allocations')]
#[ORM\Index(columns: ['prestation_id'], name: 'idx_ppa_prestation')]
#[ORM\Index(columns: ['transaction_id'], name: 'idx_ppa_transaction')]
class PrestationPaymentAllocation
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $transactionId;

    #[ORM\Column(type: 'uuid')]
    private Uuid $prestationId;

    #[ORM\Column(type: 'float')]
    private float $amount;

    public function __construct(Uuid $transactionId, Uuid $prestationId, float $amount)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->transactionId = $transactionId;
        $this->prestationId = $prestationId;
        $this->amount = $amount;
    }

    public function getTransactionId(): Uuid
    {
        return $this->transactionId;
    }

    public function getPrestationId(): Uuid
    {
        return $this->prestationId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
        $this->touch();
    }
}

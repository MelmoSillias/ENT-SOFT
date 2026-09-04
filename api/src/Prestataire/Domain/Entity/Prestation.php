<?php

namespace App\Prestataire\Domain\Entity;

use App\Prestataire\Domain\Enum\PrestationPaymentStatus;
use App\Prestataire\Domain\Enum\PrestationWorkStatus;
use App\Prestataire\Infrastructure\Persistence\Doctrine\DoctrinePrestationRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrinePrestationRepository::class)]
#[ORM\Table(name: 'prestations')]
#[ORM\Index(columns: ['prestataire_id'], name: 'idx_prestation_prestataire')]
class Prestation
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $prestataireId;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $siteId;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(enumType: PrestationWorkStatus::class, length: 30)]
    private PrestationWorkStatus $workStatus;

    #[ORM\Column(enumType: PrestationPaymentStatus::class, length: 30)]
    private PrestationPaymentStatus $paymentStatus;

    public function __construct(
        Uuid $prestataireId,
        string $description,
        float $amount,
        PrestationWorkStatus $workStatus = PrestationWorkStatus::PENDING,
        PrestationPaymentStatus $paymentStatus = PrestationPaymentStatus::UNPAID,
        ?Uuid $siteId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->prestataireId = $prestataireId;
        $this->description = $description;
        $this->amount = $amount;
        $this->workStatus = $workStatus;
        $this->paymentStatus = $paymentStatus;
        $this->siteId = $siteId;
    }

    public function getPrestataireId(): Uuid { return $this->prestataireId; }
    public function getDescription(): string { return $this->description; }
    public function getSiteId(): ?Uuid { return $this->siteId; }
    public function getAmount(): float { return $this->amount; }
    public function getWorkStatus(): PrestationWorkStatus { return $this->workStatus; }
    public function getPaymentStatus(): PrestationPaymentStatus { return $this->paymentStatus; }

    public function setDescription(string $description): void { $this->description = $description; $this->touch(); }
    public function setSiteId(?Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
    public function setAmount(float $amount): void { $this->amount = $amount; $this->touch(); }
    public function setWorkStatus(PrestationWorkStatus $workStatus): void { $this->workStatus = $workStatus; $this->touch(); }
    public function setPaymentStatus(PrestationPaymentStatus $paymentStatus): void { $this->paymentStatus = $paymentStatus; $this->touch(); }
}

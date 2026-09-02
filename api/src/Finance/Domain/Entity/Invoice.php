<?php

namespace App\Finance\Domain\Entity;

use App\Finance\Domain\Enum\InvoiceStatus;
use App\Finance\Infrastructure\Persistence\Doctrine\DoctrineInvoiceRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineInvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', fields: ['number'])]
class Invoice
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $number;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(enumType: InvoiceStatus::class)]
    private InvoiceStatus $status;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectId;

    public function __construct(
        string $number,
        \DateTimeImmutable $date,
        float $amount,
        Uuid $clientId,
        InvoiceStatus $status = InvoiceStatus::DRAFT,
        ?Uuid $projectId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->number = $number;
        $this->date = $date;
        $this->amount = $amount;
        $this->clientId = $clientId;
        $this->status = $status;
        $this->projectId = $projectId;
    }

    public function getNumber(): string { return $this->number; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): InvoiceStatus { return $this->status; }
    public function getClientId(): Uuid { return $this->clientId; }
    public function getProjectId(): ?Uuid { return $this->projectId; }

    public function setDate(\DateTimeImmutable $date): void { $this->date = $date; $this->touch(); }
    public function setAmount(float $amount): void { $this->amount = $amount; $this->touch(); }
    public function setStatus(InvoiceStatus $status): void { $this->status = $status; $this->touch(); }
    public function setClientId(Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    public function setProjectId(?Uuid $projectId): void { $this->projectId = $projectId; $this->touch(); }
}

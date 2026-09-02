<?php

namespace App\Finance\Domain\Entity;

use App\Finance\Domain\Enum\TransactionCategory;
use App\Finance\Domain\Enum\TransactionStatus;
use App\Finance\Domain\Enum\TransactionType;
use App\Finance\Infrastructure\Persistence\Doctrine\DoctrineFinancialTransactionRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineFinancialTransactionRepository::class)]
#[ORM\Table(name: 'financial_transactions')]
class FinancialTransaction
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(enumType: TransactionCategory::class)]
    private TransactionCategory $category;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(enumType: TransactionStatus::class)]
    private TransactionStatus $status;

    #[ORM\Column(length: 255)]
    private string $fromParty;

    #[ORM\Column(length: 255)]
    private string $toParty;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $clientId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $siteId;

    public function __construct(
        \DateTimeImmutable $date,
        float $amount,
        TransactionType $type,
        TransactionCategory $category,
        TransactionStatus $status,
        string $fromParty,
        string $toParty,
        ?string $description = null,
        ?Uuid $clientId = null,
        ?Uuid $projectId = null,
        ?Uuid $siteId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->date = $date;
        $this->amount = $amount;
        $this->type = $type;
        $this->category = $category;
        $this->status = $status;
        $this->fromParty = $fromParty;
        $this->toParty = $toParty;
        $this->description = $description;
        $this->clientId = $clientId;
        $this->projectId = $projectId;
        $this->siteId = $siteId;
    }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getAmount(): float { return $this->amount; }
    public function getType(): TransactionType { return $this->type; }
    public function getCategory(): TransactionCategory { return $this->category; }
    public function getDescription(): ?string { return $this->description; }
    public function getStatus(): TransactionStatus { return $this->status; }
    public function getFromParty(): string { return $this->fromParty; }
    public function getToParty(): string { return $this->toParty; }
    public function getClientId(): ?Uuid { return $this->clientId; }
    public function getProjectId(): ?Uuid { return $this->projectId; }
    public function getSiteId(): ?Uuid { return $this->siteId; }

    public function setDate(\DateTimeImmutable $date): void { $this->date = $date; $this->touch(); }
    public function setAmount(float $amount): void { $this->amount = $amount; $this->touch(); }
    public function setType(TransactionType $type): void { $this->type = $type; $this->touch(); }
    public function setCategory(TransactionCategory $category): void { $this->category = $category; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setStatus(TransactionStatus $status): void { $this->status = $status; $this->touch(); }
    public function setFromParty(string $fromParty): void { $this->fromParty = $fromParty; $this->touch(); }
    public function setToParty(string $toParty): void { $this->toParty = $toParty; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    public function setProjectId(?Uuid $projectId): void { $this->projectId = $projectId; $this->touch(); }
    public function setSiteId(?Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
}

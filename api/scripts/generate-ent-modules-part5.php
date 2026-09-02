<?php

declare(strict_types=1);

// ========== FINANCE MODULE ==========

w('Finance/Domain/Enum/InvoiceStatus.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Enum;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}

PHP);

w('Finance/Domain/Enum/TransactionType.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Enum;

enum TransactionType: string
{
    case INCOME = 'income';
    case EXPENSE = 'expense';
}

PHP);

w('Finance/Domain/Enum/TransactionCategory.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Enum;

enum TransactionCategory: string
{
    case INVOICE_PAYMENT = 'InvoicePayment';
    case PROJECT_EXPENSE = 'ProjetExpense';
    case SITE_EXPENSE = 'SiteExpense';
    case MATERIAL_EXPENSE = 'MaterialExpense';
    case EQUIPMENT_EXPENSE = 'EquipmentExpense';
    case OTHER_EXPENSE = 'OtherExpense';
}

PHP);

w('Finance/Domain/Enum/TransactionStatus.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Enum;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

PHP);

w('Finance/Domain/Entity/Invoice.php', <<<'PHP'
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

PHP);

w('Finance/Domain/Entity/FinancialTransaction.php', <<<'PHP'
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

PHP);

writeException('Finance', 'Invoice', 'Facture');
writeException('Finance', 'FinancialTransaction', 'Transaction');

w('Finance/Domain/Repository/InvoiceRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Repository;

use App\Finance\Domain\Entity\Invoice;
use App\Finance\Domain\Enum\InvoiceStatus;
use Symfony\Component\Uid\Uuid;

interface InvoiceRepositoryInterface
{
    public function save(Invoice $invoice): void;

    public function findById(Uuid $id): ?Invoice;

    /** @return list<Invoice> */
    public function findAllEnabled(): array;

    public function countByClientId(Uuid $clientId): int;

    public function countByStatus(InvoiceStatus $status): int;
}

PHP);

w('Finance/Domain/Repository/FinancialTransactionRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Finance\Domain\Repository;

use App\Finance\Domain\Entity\FinancialTransaction;
use Symfony\Component\Uid\Uuid;

interface FinancialTransactionRepositoryInterface
{
    public function save(FinancialTransaction $transaction): void;

    public function findById(Uuid $id): ?FinancialTransaction;

    /** @return list<FinancialTransaction> */
    public function findAllEnabled(): array;
}

PHP);

require __DIR__ . '/generate-ent-modules-part5b.php';

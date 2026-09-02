<?php

declare(strict_types=1);

// ========== STOCK MODULE ==========

w('Stock/Domain/Entity/Equipment.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Stock\Infrastructure\Persistence\Doctrine\DoctrineEquipmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineEquipmentRepository::class)]
#[ORM\Table(name: 'equipment')]
#[ORM\UniqueConstraint(name: 'uniq_equipment_code', fields: ['code'])]
class Equipment
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $clientId;

    public function __construct(string $code, string $title, ?string $description = null, ?Uuid $clientId = null)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
        $this->clientId = $clientId;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getClientId(): ?Uuid { return $this->clientId; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
}

PHP);

w('Stock/Domain/Entity/StockMovement.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Stock\Infrastructure\Persistence\Doctrine\DoctrineStockMovementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineStockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements')]
class StockMovement
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'float')]
    private float $quantity;

    #[ORM\Column(length: 50)]
    private string $unit;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $clientId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $projectId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $siteId;

    public function __construct(
        \DateTimeImmutable $date,
        float $quantity,
        string $unit,
        ?Uuid $clientId = null,
        ?Uuid $projectId = null,
        ?Uuid $siteId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->date = $date;
        $this->quantity = $quantity;
        $this->unit = $unit;
        $this->clientId = $clientId;
        $this->projectId = $projectId;
        $this->siteId = $siteId;
    }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnit(): string { return $this->unit; }
    public function getClientId(): ?Uuid { return $this->clientId; }
    public function getProjectId(): ?Uuid { return $this->projectId; }
    public function getSiteId(): ?Uuid { return $this->siteId; }

    public function setDate(\DateTimeImmutable $date): void { $this->date = $date; $this->touch(); }
    public function setQuantity(float $quantity): void { $this->quantity = $quantity; $this->touch(); }
    public function setUnit(string $unit): void { $this->unit = $unit; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    public function setProjectId(?Uuid $projectId): void { $this->projectId = $projectId; $this->touch(); }
    public function setSiteId(?Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
}

PHP);

w('Stock/Domain/Entity/StockMovementLine.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Entity;

use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Stock\Infrastructure\Persistence\Doctrine\DoctrineStockMovementLineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineStockMovementLineRepository::class)]
#[ORM\Table(name: 'stock_movement_lines')]
class StockMovementLine
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(targetEntity: StockMovement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private StockMovement $movement;

    #[ORM\Column(type: 'uuid')]
    private Uuid $equipmentId;

    #[ORM\Column(type: 'float')]
    private float $quantity;

    public function __construct(StockMovement $movement, Uuid $equipmentId, float $quantity)
    {
        $this->initializeUuid();
        $this->movement = $movement;
        $this->equipmentId = $equipmentId;
        $this->quantity = $quantity;
    }

    public function getMovement(): StockMovement { return $this->movement; }
    public function getEquipmentId(): Uuid { return $this->equipmentId; }
    public function getQuantity(): float { return $this->quantity; }

    public function setQuantity(float $quantity): void { $this->quantity = $quantity; }
}

PHP);

writeException('Stock', 'Equipment', 'Équipement');
writeException('Stock', 'StockMovement', 'Mouvement de stock');

w('Stock/Domain/Repository/EquipmentRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\Equipment;
use Symfony\Component\Uid\Uuid;

interface EquipmentRepositoryInterface
{
    public function save(Equipment $equipment): void;

    public function findById(Uuid $id): ?Equipment;

    /** @return list<Equipment> */
    public function findAllEnabled(?string $search = null): array;
}

PHP);

w('Stock/Domain/Repository/StockMovementRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\StockMovement;
use Symfony\Component\Uid\Uuid;

interface StockMovementRepositoryInterface
{
    public function save(StockMovement $movement): void;

    public function findById(Uuid $id): ?StockMovement;

    /** @return list<StockMovement> */
    public function findAllEnabled(): array;
}

PHP);

w('Stock/Domain/Repository/StockMovementLineRepositoryInterface.php', <<<'PHP'
<?php

namespace App\Stock\Domain\Repository;

use App\Stock\Domain\Entity\StockMovementLine;
use Symfony\Component\Uid\Uuid;

interface StockMovementLineRepositoryInterface
{
    public function save(StockMovementLine $line): void;

    /** @return list<StockMovementLine> */
    public function findByMovementId(Uuid $movementId): array;
}

PHP);

require __DIR__ . '/generate-ent-modules-part6b.php';

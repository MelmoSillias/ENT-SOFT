<?php

namespace App\Stock\Domain\Entity;

use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use App\Stock\Domain\Enum\StockMovementDirection;
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

    #[ORM\Column(enumType: StockMovementDirection::class)]
    private StockMovementDirection $direction;

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
        StockMovementDirection $direction = StockMovementDirection::IN,
        ?Uuid $clientId = null,
        ?Uuid $projectId = null,
        ?Uuid $siteId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->date = $date;
        $this->quantity = $quantity;
        $this->unit = $unit;
        $this->direction = $direction;
        $this->clientId = $clientId;
        $this->projectId = $projectId;
        $this->siteId = $siteId;
    }

    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnit(): string { return $this->unit; }
    public function getDirection(): StockMovementDirection { return $this->direction; }
    public function getClientId(): ?Uuid { return $this->clientId; }
    public function getProjectId(): ?Uuid { return $this->projectId; }
    public function getSiteId(): ?Uuid { return $this->siteId; }

    public function setDate(\DateTimeImmutable $date): void { $this->date = $date; $this->touch(); }
    public function setQuantity(float $quantity): void { $this->quantity = $quantity; $this->touch(); }
    public function setUnit(string $unit): void { $this->unit = $unit; $this->touch(); }
    public function setDirection(StockMovementDirection $direction): void { $this->direction = $direction; $this->touch(); }
    public function setClientId(?Uuid $clientId): void { $this->clientId = $clientId; $this->touch(); }
    public function setProjectId(?Uuid $projectId): void { $this->projectId = $projectId; $this->touch(); }
    public function setSiteId(?Uuid $siteId): void { $this->siteId = $siteId; $this->touch(); }
}

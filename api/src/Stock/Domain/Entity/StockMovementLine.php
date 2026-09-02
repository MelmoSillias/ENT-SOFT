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

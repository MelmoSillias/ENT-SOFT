<?php

namespace App\Finance\Domain\Entity;

use App\Finance\Infrastructure\Persistence\Doctrine\DoctrineInvoiceLineRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineInvoiceLineRepository::class)]
#[ORM\Table(name: 'invoice_lines')]
class InvoiceLine
{
    use UuidEntityTrait;

    #[ORM\ManyToOne(targetEntity: Invoice::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(length: 50)]
    private string $unit;

    #[ORM\Column(type: 'float')]
    private float $quantity;

    #[ORM\Column(type: 'float')]
    private float $unitPrice;

    #[ORM\Column(type: 'float')]
    private float $amount;

    public function __construct(
        Invoice $invoice,
        string $description,
        float $quantity,
        float $unitPrice,
        string $unit = 'Lot',
    ) {
        $this->initializeUuid();
        $this->invoice = $invoice;
        $this->description = $description;
        $this->unit = $unit !== '' ? $unit : 'Lot';
        $this->quantity = $quantity;
        $this->unitPrice = $unitPrice;
        $this->amount = round($quantity * $unitPrice, 2);
    }

    public function getInvoice(): Invoice { return $this->invoice; }
    public function getDescription(): string { return $this->description; }
    public function getUnit(): string { return $this->unit; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getAmount(): float { return $this->amount; }

    public function setDescription(string $description): void { $this->description = $description; }
    public function setUnit(string $unit): void { $this->unit = $unit !== '' ? $unit : 'Lot'; }
    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
        $this->amount = round($this->quantity * $this->unitPrice, 2);
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
        $this->amount = round($this->quantity * $this->unitPrice, 2);
    }
}

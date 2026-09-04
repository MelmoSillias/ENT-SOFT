<?php

namespace App\Client\Domain\Entity;

use App\Client\Infrastructure\Persistence\Doctrine\DoctrineClientRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineClientRepository::class)]
#[ORM\Table(name: 'clients')]
#[ORM\UniqueConstraint(name: 'uniq_client_code', fields: ['code'])]
class Client
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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $postalBox = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    public function __construct(
        string $code,
        string $title,
        ?string $description = null,
        ?string $address = null,
        ?string $postalBox = null,
        ?string $city = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = $code;
        $this->title = $title;
        $this->description = $description;
        $this->address = $address;
        $this->postalBox = $postalBox;
        $this->city = $city;
    }

    public function getCode(): string { return $this->code; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getAddress(): ?string { return $this->address; }
    public function getPostalBox(): ?string { return $this->postalBox; }
    public function getCity(): ?string { return $this->city; }

    public function setTitle(string $title): void { $this->title = $title; $this->touch(); }
    public function setDescription(?string $description): void { $this->description = $description; $this->touch(); }
    public function setAddress(?string $address): void { $this->address = $address; $this->touch(); }
    public function setPostalBox(?string $postalBox): void { $this->postalBox = $postalBox; $this->touch(); }
    public function setCity(?string $city): void { $this->city = $city; $this->touch(); }
}

<?php

namespace App\Employee\Domain\Entity;

use App\Employee\Infrastructure\Persistence\Doctrine\DoctrineEmployeeRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineEmployeeRepository::class)]
#[ORM\Table(name: 'employees')]
class Employee
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $phone;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address;

    #[ORM\Column(length: 100)]
    private string $function;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId;

    public function __construct(
        string $name,
        string $email,
        string $phone,
        string $function,
        ?string $address = null,
        ?Uuid $userId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->function = $function;
        $this->address = $address;
        $this->userId = $userId;
    }

    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getAddress(): ?string { return $this->address; }
    public function getFunction(): string { return $this->function; }
    public function getUserId(): ?Uuid { return $this->userId; }

    public function setName(string $name): void { $this->name = $name; $this->touch(); }
    public function setEmail(string $email): void { $this->email = $email; $this->touch(); }
    public function setPhone(string $phone): void { $this->phone = $phone; $this->touch(); }
    public function setAddress(?string $address): void { $this->address = $address; $this->touch(); }
    public function setFunction(string $function): void { $this->function = $function; $this->touch(); }
    public function setUserId(?Uuid $userId): void { $this->userId = $userId; $this->touch(); }
}

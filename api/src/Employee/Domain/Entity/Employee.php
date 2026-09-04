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

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 50)]
    private string $phone;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address;

    #[ORM\Column(length: 50)]
    private string $roleCode;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $userId;

    public function __construct(
        string $prenom,
        string $nom,
        string $email,
        string $phone,
        string $roleCode,
        ?string $address = null,
        ?Uuid $userId = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->prenom = $prenom;
        $this->nom = $nom;
        $this->email = $email;
        $this->phone = $phone;
        $this->roleCode = strtoupper(trim($roleCode));
        $this->address = $address;
        $this->userId = $userId;
    }

    public function getPrenom(): string { return $this->prenom; }
    public function getNom(): string { return $this->nom; }
    public function getFullName(): string { return trim($this->prenom.' '.$this->nom); }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getAddress(): ?string { return $this->address; }
    public function getRoleCode(): string { return $this->roleCode; }
    public function getUserId(): ?Uuid { return $this->userId; }

    public function setPrenom(string $prenom): void { $this->prenom = $prenom; $this->touch(); }
    public function setNom(string $nom): void { $this->nom = $nom; $this->touch(); }
    public function setEmail(string $email): void { $this->email = $email; $this->touch(); }
    public function setPhone(string $phone): void { $this->phone = $phone; $this->touch(); }
    public function setAddress(?string $address): void { $this->address = $address; $this->touch(); }
    public function setRoleCode(string $roleCode): void { $this->roleCode = strtoupper(trim($roleCode)); $this->touch(); }
    public function setUserId(?Uuid $userId): void { $this->userId = $userId; $this->touch(); }
}

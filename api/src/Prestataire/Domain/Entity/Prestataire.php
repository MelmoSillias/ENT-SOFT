<?php

namespace App\Prestataire\Domain\Entity;

use App\Prestataire\Infrastructure\Persistence\Doctrine\DoctrinePrestataireRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrinePrestataireRepository::class)]
#[ORM\Table(name: 'prestataires')]
class Prestataire
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

    public function __construct(
        string $prenom,
        string $nom,
        string $email,
        string $phone,
        ?string $address = null,
    ) {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->prenom = $prenom;
        $this->nom = $nom;
        $this->email = $email;
        $this->phone = $phone;
        $this->address = $address;
    }

    public function getPrenom(): string { return $this->prenom; }
    public function getNom(): string { return $this->nom; }
    public function getFullName(): string { return trim($this->prenom.' '.$this->nom); }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getAddress(): ?string { return $this->address; }

    public function setPrenom(string $prenom): void { $this->prenom = $prenom; $this->touch(); }
    public function setNom(string $nom): void { $this->nom = $nom; $this->touch(); }
    public function setEmail(string $email): void { $this->email = $email; $this->touch(); }
    public function setPhone(string $phone): void { $this->phone = $phone; $this->touch(); }
    public function setAddress(?string $address): void { $this->address = $address; $this->touch(); }
}

<?php

namespace App\Client\Domain\Entity;

use App\Client\Infrastructure\Persistence\Doctrine\DoctrineClientContactRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineClientContactRepository::class)]
#[ORM\Table(name: 'client_contacts')]
class ClientContact
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $phone;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $clientId, string $name, string $phone)
    {
        $this->initializeUuid();
        $this->clientId = $clientId;
        $this->name = $name;
        $this->phone = $phone;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getClientId(): Uuid { return $this->clientId; }
    public function getName(): string { return $this->name; }
    public function getPhone(): string { return $this->phone; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setName(string $name): void { $this->name = $name; }
    public function setPhone(string $phone): void { $this->phone = $phone; }
}

<?php

namespace App\Client\Domain\Entity;

use App\Client\Infrastructure\Persistence\Doctrine\DoctrineClientCommentRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineClientCommentRepository::class)]
#[ORM\Table(name: 'client_comments')]
class ClientComment
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $clientId;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $clientId, string $content)
    {
        $this->initializeUuid();
        $this->clientId = $clientId;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getClientId(): Uuid { return $this->clientId; }
    public function getContent(): string { return $this->content; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

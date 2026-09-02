<?php

namespace App\IdentityAccess\Domain\Entity;

use App\IdentityAccess\Infrastructure\Persistence\Doctrine\DoctrineRefreshTokenRepository;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineRefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    use UuidEntityTrait;
    use TimestampableTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $utilisateurId;

    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(Uuid $utilisateurId, string $tokenHash, \DateTimeImmutable $expiresAt)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->utilisateurId = $utilisateurId;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
    }

    public function getUtilisateurId(): Uuid
    {
        return $this->utilisateurId;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}

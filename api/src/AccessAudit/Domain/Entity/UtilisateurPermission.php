<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrineUtilisateurPermissionRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineUtilisateurPermissionRepository::class)]
#[ORM\Table(name: 'utilisateur_permissions')]
#[ORM\UniqueConstraint(name: 'uniq_utilisateur_permission', fields: ['utilisateurId', 'permission'])]
class UtilisateurPermission
{
    use UuidEntityTrait;

    #[ORM\Column(type: 'uuid')]
    private Uuid $utilisateurId;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Permission $permission;

    #[ORM\Column]
    private bool $accorde;

    #[ORM\Column(type: 'uuid')]
    private Uuid $attribueParId;

    #[ORM\Column]
    private \DateTimeImmutable $dateAttribution;

    public function __construct(
        Uuid $utilisateurId,
        Permission $permission,
        bool $accorde,
        Uuid $attribueParId,
    ) {
        $this->initializeUuid();
        $this->utilisateurId = $utilisateurId;
        $this->permission = $permission;
        $this->accorde = $accorde;
        $this->attribueParId = $attribueParId;
        $this->dateAttribution = new \DateTimeImmutable();
    }

    public function getUtilisateurId(): Uuid
    {
        return $this->utilisateurId;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }

    public function isAccorde(): bool
    {
        return $this->accorde;
    }

    public function setAccorde(bool $accorde): void
    {
        $this->accorde = $accorde;
        $this->dateAttribution = new \DateTimeImmutable();
    }

    public function getAttribueParId(): Uuid
    {
        return $this->attribueParId;
    }

    public function getDateAttribution(): \DateTimeImmutable
    {
        return $this->dateAttribution;
    }
}

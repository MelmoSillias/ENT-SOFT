<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrineAppRoleRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\TimestampableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineAppRoleRepository::class)]
#[ORM\Table(name: 'app_roles')]
#[ORM\UniqueConstraint(name: 'uniq_app_role_code', fields: ['code'])]
class AppRole
{
    use UuidEntityTrait;
    use TimestampableTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 50)]
    private string $code;

    #[ORM\Column(length: 100)]
    private string $libelle;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

    public function __construct(string $code, string $libelle, bool $isSystem = false)
    {
        $this->initializeUuid();
        $this->initializeTimestamps();
        $this->code = strtoupper(trim($code));
        $this->libelle = $libelle;
        $this->isSystem = $isSystem;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setLibelle(string $libelle): void
    {
        $this->libelle = $libelle;
        $this->touch();
    }
}

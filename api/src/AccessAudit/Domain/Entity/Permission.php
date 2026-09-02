<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrinePermissionRepository;
use App\SharedKernel\Domain\Trait\SoftDeletableTrait;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrinePermissionRepository::class)]
#[ORM\Table(name: 'permissions')]
#[ORM\UniqueConstraint(name: 'uniq_permission_code', fields: ['code'])]
class Permission
{
    use UuidEntityTrait;
    use SoftDeletableTrait;

    #[ORM\Column(length: 100)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description;

    #[ORM\Column(length: 50)]
    private string $module;

    public function __construct(string $code, string $libelle, string $module, ?string $description = null)
    {
        $this->initializeUuid();
        $this->code = $code;
        $this->libelle = $libelle;
        $this->module = $module;
        $this->description = $description;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getModule(): string
    {
        return $this->module;
    }
}

<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrineRolePermissionRepository;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineRolePermissionRepository::class)]
#[ORM\Table(name: 'role_permissions')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', fields: ['roleCode', 'permission'])]
class RolePermission
{
    use UuidEntityTrait;

    #[ORM\Column(name: 'role', length: 50)]
    private string $roleCode;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Permission $permission;

    public function __construct(string $roleCode, Permission $permission)
    {
        $this->initializeUuid();
        $this->roleCode = strtoupper(trim($roleCode));
        $this->permission = $permission;
    }

    public function getRoleCode(): string
    {
        return $this->roleCode;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }
}

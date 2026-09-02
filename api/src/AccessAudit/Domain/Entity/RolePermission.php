<?php

namespace App\AccessAudit\Domain\Entity;

use App\AccessAudit\Infrastructure\Persistence\Doctrine\DoctrineRolePermissionRepository;
use App\IdentityAccess\Domain\Enum\Role;
use App\SharedKernel\Domain\Trait\UuidEntityTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctrineRolePermissionRepository::class)]
#[ORM\Table(name: 'role_permissions')]
#[ORM\UniqueConstraint(name: 'uniq_role_permission', fields: ['role', 'permission'])]
class RolePermission
{
    use UuidEntityTrait;

    #[ORM\Column(enumType: Role::class)]
    private Role $role;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Permission $permission;

    public function __construct(Role $role, Permission $permission)
    {
        $this->initializeUuid();
        $this->role = $role;
        $this->permission = $permission;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getPermission(): Permission
    {
        return $this->permission;
    }
}

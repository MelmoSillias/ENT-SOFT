<?php

namespace App\AccessAudit\Domain\Repository;

use App\AccessAudit\Domain\Entity\RolePermission;
use App\IdentityAccess\Domain\Enum\Role;

interface RolePermissionRepositoryInterface
{
    /** @return list<RolePermission> */
    public function findByRole(Role $role): array;

    /** @return list<string> */
    public function findPermissionCodesByRole(Role $role): array;

    public function save(RolePermission $rolePermission, bool $flush = true): void;
}

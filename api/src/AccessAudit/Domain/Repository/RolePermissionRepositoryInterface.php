<?php

namespace App\AccessAudit\Domain\Repository;

use App\AccessAudit\Domain\Entity\RolePermission;

interface RolePermissionRepositoryInterface
{
    /** @return list<RolePermission> */
    public function findByRoleCode(string $roleCode): array;

    /** @return list<string> */
    public function findPermissionCodesByRoleCode(string $roleCode): array;

    /** @return array<string, list<string>> */
    public function findAllGroupedByRole(): array;

    public function save(RolePermission $rolePermission, bool $flush = true): void;

    public function remove(RolePermission $rolePermission, bool $flush = true): void;
}

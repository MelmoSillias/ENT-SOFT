<?php

namespace App\AccessAudit\Application\Query\ListAppRoles;

use App\AccessAudit\Application\Dto\AppRoleResponseDto;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;

final class ListAppRolesHandler
{
    public function __construct(
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function handle(ListAppRolesQuery $query): array
    {
        $grouped = $this->rolePermissionRepository->findAllGroupedByRole();
        $result = [];

        foreach ($this->appRoleRepository->findAllOrdered($query->enabledOnly) as $role) {
            $result[] = AppRoleResponseDto::fromEntity(
                $role,
                $grouped[$role->getCode()] ?? [],
            )->toArray();
        }

        return $result;
    }
}

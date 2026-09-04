<?php

namespace App\AccessAudit\Application\Query\GetAppRole;

use App\AccessAudit\Application\Dto\AppRoleResponseDto;
use App\AccessAudit\Domain\Exception\AppRoleNotFoundException;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class GetAppRoleHandler
{
    public function __construct(
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {
    }

    public function handle(GetAppRoleQuery $query): AppRoleResponseDto
    {
        $role = $this->appRoleRepository->findById(Uuid::fromString($query->id));
        if (null === $role) {
            throw new AppRoleNotFoundException($query->id);
        }

        $codes = $this->rolePermissionRepository->findPermissionCodesByRoleCode($role->getCode());

        return AppRoleResponseDto::fromEntity($role, $codes);
    }
}

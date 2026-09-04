<?php

namespace App\AccessAudit\Application\Command\DeleteAppRole;

use App\AccessAudit\Application\Dto\AppRoleResponseDto;
use App\AccessAudit\Domain\Exception\AppRoleNotFoundException;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class DeleteAppRoleHandler
{
    public function __construct(
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {
    }

    public function handle(DeleteAppRoleCommand $command): AppRoleResponseDto
    {
        $role = $this->appRoleRepository->findById(Uuid::fromString($command->id));
        if (null === $role || !$role->isEnabled()) {
            throw new AppRoleNotFoundException($command->id);
        }

        if ($role->isSystem()) {
            throw new \InvalidArgumentException('Impossible de masquer un rôle système.');
        }

        $role->disable();
        $this->appRoleRepository->save($role);
        $codes = $this->rolePermissionRepository->findPermissionCodesByRoleCode($role->getCode());

        return AppRoleResponseDto::fromEntity($role, $codes);
    }
}

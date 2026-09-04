<?php

namespace App\AccessAudit\Application\Command\UpdateAppRole;

use App\AccessAudit\Application\Dto\AppRoleResponseDto;
use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\Exception\AppRoleNotFoundException;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\AccessAudit\Domain\Repository\PermissionRepositoryInterface;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;
use Symfony\Component\Uid\Uuid;

final class UpdateAppRoleHandler
{
    public function __construct(
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly PermissionRepositoryInterface $permissionRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {
    }

    public function handle(UpdateAppRoleCommand $command): AppRoleResponseDto
    {
        $role = $this->appRoleRepository->findById(Uuid::fromString($command->id));
        if (null === $role || !$role->isEnabled()) {
            throw new AppRoleNotFoundException($command->id);
        }

        if (null !== $command->libelle) {
            $role->setLibelle(FieldValidator::requireNonEmpty($command->libelle, 'Libellé'));
        }

        if ($command->hasPermissionCodes) {
            foreach ($this->rolePermissionRepository->findByRoleCode($role->getCode()) as $existing) {
                $this->rolePermissionRepository->remove($existing, false);
            }

            foreach ($command->permissionCodes ?? [] as $permCode) {
                $permission = $this->permissionRepository->findByCode((string) $permCode);
                if (null === $permission) {
                    continue;
                }
                $this->rolePermissionRepository->save(new RolePermission($role->getCode(), $permission), false);
            }
        }

        $this->appRoleRepository->save($role);
        $codes = $this->rolePermissionRepository->findPermissionCodesByRoleCode($role->getCode());

        return AppRoleResponseDto::fromEntity($role, $codes);
    }
}

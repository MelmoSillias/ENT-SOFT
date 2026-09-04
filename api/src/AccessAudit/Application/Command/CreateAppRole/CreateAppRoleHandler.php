<?php

namespace App\AccessAudit\Application\Command\CreateAppRole;

use App\AccessAudit\Application\Dto\AppRoleResponseDto;
use App\AccessAudit\Domain\Entity\AppRole;
use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\Repository\AppRoleRepositoryInterface;
use App\AccessAudit\Domain\Repository\PermissionRepositoryInterface;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use App\SharedKernel\Domain\Validation\FieldValidator;

final class CreateAppRoleHandler
{
    public function __construct(
        private readonly AppRoleRepositoryInterface $appRoleRepository,
        private readonly PermissionRepositoryInterface $permissionRepository,
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {
    }

    public function handle(CreateAppRoleCommand $command): AppRoleResponseDto
    {
        $code = strtoupper(FieldValidator::requireNonEmpty($command->code, 'Code'));
        $libelle = FieldValidator::requireNonEmpty($command->libelle, 'Libellé');

        if (!preg_match('/^[A-Z][A-Z0-9_]{1,48}$/', $code)) {
            throw new \InvalidArgumentException('Code rôle invalide (lettres majuscules, chiffres, underscore).');
        }

        if (null !== $this->appRoleRepository->findByCode($code)) {
            throw new \InvalidArgumentException('Ce code de rôle existe déjà.');
        }

        $role = new AppRole($code, $libelle, false);
        $this->appRoleRepository->save($role, false);

        $codes = [];
        foreach ($command->permissionCodes ?? [] as $permCode) {
            $permission = $this->permissionRepository->findByCode((string) $permCode);
            if (null === $permission) {
                continue;
            }
            $this->rolePermissionRepository->save(new RolePermission($code, $permission), false);
            $codes[] = $permission->getCode();
        }

        $this->appRoleRepository->save($role);
        sort($codes);

        return AppRoleResponseDto::fromEntity($role, $codes);
    }
}

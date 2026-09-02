<?php

namespace App\AccessAudit\Application\Service;

use App\AccessAudit\Domain\PermissionCatalog;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use App\AccessAudit\Domain\Repository\UtilisateurPermissionRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Enum\Role;

final class PermissionResolverService
{
    public function __construct(
        private readonly RolePermissionRepositoryInterface $rolePermissionRepository,
        private readonly UtilisateurPermissionRepositoryInterface $utilisateurPermissionRepository,
    ) {
    }

    /** @return list<string> */
    public function resolvePermissions(Utilisateur $utilisateur): array
    {
        $permissions = [];

        $roleCodes = $this->rolePermissionRepository->findPermissionCodesByRole($utilisateur->getRole());
        if ([] === $roleCodes) {
            $roleCodes = PermissionCatalog::rolePermissions()[$utilisateur->getRole()->value] ?? [];
        }

        foreach ($roleCodes as $code) {
            $permissions[$code] = true;
        }

        foreach ($this->utilisateurPermissionRepository->findByUtilisateurId($utilisateur->getId()) as $override) {
            if (!$override->getPermission()->isEnabled()) {
                continue;
            }

            $code = $override->getPermission()->getCode();
            if ($override->isAccorde()) {
                $permissions[$code] = true;
            } else {
                unset($permissions[$code]);
            }
        }

        ksort($permissions);

        return array_keys($permissions);
    }

    public function hasPermission(Utilisateur $utilisateur, string $permission): bool
    {
        return in_array($permission, $this->resolvePermissions($utilisateur), true);
    }

    /** @return list<string> */
    public function resolveDefaultPermissionsForRole(Role $role): array
    {
        $dbCodes = $this->rolePermissionRepository->findPermissionCodesByRole($role);
        if ([] !== $dbCodes) {
            return $dbCodes;
        }

        return PermissionCatalog::rolePermissions()[$role->value] ?? [];
    }
}

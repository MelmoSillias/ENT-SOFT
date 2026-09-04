<?php

namespace App\AccessAudit\Application\Service;

use App\AccessAudit\Domain\PermissionCatalog;
use App\AccessAudit\Domain\Repository\RolePermissionRepositoryInterface;
use App\AccessAudit\Domain\Repository\UtilisateurPermissionRepositoryInterface;
use App\IdentityAccess\Domain\Entity\Utilisateur;

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
        $roleCode = $utilisateur->getRoleCode();

        $roleCodes = $this->rolePermissionRepository->findPermissionCodesByRoleCode($roleCode);
        if ([] === $roleCodes) {
            $roleCodes = PermissionCatalog::rolePermissions()[$roleCode] ?? [];
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
    public function resolveDefaultPermissionsForRole(string $roleCode): array
    {
        $dbCodes = $this->rolePermissionRepository->findPermissionCodesByRoleCode($roleCode);
        if ([] !== $dbCodes) {
            return $dbCodes;
        }

        return PermissionCatalog::rolePermissions()[strtoupper(trim($roleCode))] ?? [];
    }
}

<?php

namespace App\Referentiel\Application\Service;

use App\AccessAudit\Domain\Entity\Permission;
use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\PermissionCatalog;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
use App\IdentityAccess\Domain\Enum\Role;
use App\Referentiel\Domain\Catalog\SettingsCatalog;
use Doctrine\ORM\EntityManagerInterface;

final class ReferentielBootstrapService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepositoryInterface $settingRepository,
    ) {
    }

    public function bootstrap(): void
    {
        $this->bootstrapPermissions();
        $this->bootstrapSettings();
        $this->entityManager->flush();
    }

    private function bootstrapPermissions(): void
    {
        $permissionRepo = $this->entityManager->getRepository(Permission::class);

        foreach (PermissionCatalog::all() as $def) {
            if (null !== $permissionRepo->findOneBy(['code' => $def['code']])) {
                continue;
            }
            $this->entityManager->persist(new Permission(
                $def['code'],
                $def['libelle'],
                $def['module'],
                $def['description'],
            ));
        }

        $this->entityManager->flush();

        $rolePermissionRepo = $this->entityManager->getRepository(RolePermission::class);

        foreach (PermissionCatalog::rolePermissions() as $roleValue => $codes) {
            $role = Role::from($roleValue);
            $allowed = array_fill_keys($codes, true);

            foreach ($rolePermissionRepo->findBy(['role' => $role]) as $existing) {
                $code = $existing->getPermission()->getCode();
                if (!isset($allowed[$code])) {
                    $this->entityManager->remove($existing);
                }
            }

            foreach ($codes as $code) {
                $permission = $permissionRepo->findOneBy(['code' => $code]);
                if (null === $permission) {
                    continue;
                }
                $exists = $rolePermissionRepo->findOneBy([
                    'role' => $role,
                    'permission' => $permission,
                ]);
                if (null !== $exists) {
                    continue;
                }
                $this->entityManager->persist(new RolePermission($role, $permission));
            }
        }
    }

    private function bootstrapSettings(): void
    {
        foreach (SettingsCatalog::bootstrapSettings() as $def) {
            if (null !== $this->settingRepository->findByCle($def['cle'])) {
                continue;
            }

            $this->settingRepository->save(new Setting(
                cle: $def['cle'],
                valeur: $def['valeur'],
                type: $def['type'],
                description: $def['description'],
            ), false);
        }
    }
}

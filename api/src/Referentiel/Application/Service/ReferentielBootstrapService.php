<?php

namespace App\Referentiel\Application\Service;

use App\AccessAudit\Domain\Entity\AppRole;
use App\AccessAudit\Domain\Entity\Permission;
use App\AccessAudit\Domain\Entity\RolePermission;
use App\AccessAudit\Domain\PermissionCatalog;
use App\Configuration\Domain\Entity\Setting;
use App\Configuration\Domain\Repository\SettingRepositoryInterface;
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
        $roleRepo = $this->entityManager->getRepository(AppRole::class);
        $rolePermissionRepo = $this->entityManager->getRepository(RolePermission::class);

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

        foreach (PermissionCatalog::roles() as $def) {
            if (null !== $roleRepo->findOneBy(['code' => $def['code']])) {
                continue;
            }
            $this->entityManager->persist(new AppRole($def['code'], $def['libelle'], $def['isSystem']));
        }

        $this->entityManager->flush();

        $systemCodes = [];
        foreach (PermissionCatalog::roles() as $def) {
            if ($def['isSystem']) {
                $systemCodes[$def['code']] = true;
            }
        }

        foreach (PermissionCatalog::rolePermissions() as $roleValue => $codes) {
            $allowed = array_fill_keys($codes, true);
            $existing = $rolePermissionRepo->findBy(['roleCode' => $roleValue]);
            $isSystem = isset($systemCodes[$roleValue]);
            $hasAny = [] !== $existing;

            // Rôles système : sync strict. Rôles métier : seed seulement si vide.
            if ($isSystem) {
                foreach ($existing as $rp) {
                    $code = $rp->getPermission()->getCode();
                    if (!isset($allowed[$code])) {
                        $this->entityManager->remove($rp);
                    }
                }
            } elseif ($hasAny) {
                continue;
            }

            foreach ($codes as $code) {
                $permission = $permissionRepo->findOneBy(['code' => $code]);
                if (null === $permission) {
                    continue;
                }
                $exists = $rolePermissionRepo->findOneBy([
                    'roleCode' => $roleValue,
                    'permission' => $permission,
                ]);
                if (null !== $exists) {
                    continue;
                }
                $this->entityManager->persist(new RolePermission($roleValue, $permission));
            }
        }
    }

    private function bootstrapSettings(): void
    {
        foreach (\App\Referentiel\Domain\Catalog\SettingsCatalog::bootstrapSettings() as $def) {
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

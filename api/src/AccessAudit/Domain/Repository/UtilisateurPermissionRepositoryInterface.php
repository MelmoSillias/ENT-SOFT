<?php

namespace App\AccessAudit\Domain\Repository;

use App\AccessAudit\Domain\Entity\UtilisateurPermission;
use Symfony\Component\Uid\Uuid;

interface UtilisateurPermissionRepositoryInterface
{
    /** @return list<UtilisateurPermission> */
    public function findByUtilisateurId(Uuid $utilisateurId): array;

    public function findOneByUtilisateurAndPermission(Uuid $utilisateurId, Uuid $permissionId): ?UtilisateurPermission;

    public function save(UtilisateurPermission $utilisateurPermission, bool $flush = true): void;

    public function remove(UtilisateurPermission $utilisateurPermission, bool $flush = true): void;
}

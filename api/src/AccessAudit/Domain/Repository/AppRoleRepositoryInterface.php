<?php

namespace App\AccessAudit\Domain\Repository;

use App\AccessAudit\Domain\Entity\AppRole;
use Symfony\Component\Uid\Uuid;

interface AppRoleRepositoryInterface
{
    public function findById(Uuid $id): ?AppRole;

    public function findByCode(string $code): ?AppRole;

    /** @return list<AppRole> */
    public function findAllOrdered(bool $enabledOnly = false): array;

    public function save(AppRole $role, bool $flush = true): void;
}

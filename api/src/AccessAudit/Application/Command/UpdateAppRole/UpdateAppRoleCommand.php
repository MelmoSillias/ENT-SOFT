<?php

namespace App\AccessAudit\Application\Command\UpdateAppRole;

final readonly class UpdateAppRoleCommand
{
    /** @param list<string>|null $permissionCodes */
    public function __construct(
        public string $id,
        public ?string $libelle = null,
        public ?array $permissionCodes = null,
        public bool $hasPermissionCodes = false,
    ) {
    }
}

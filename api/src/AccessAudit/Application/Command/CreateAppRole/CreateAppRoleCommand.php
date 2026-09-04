<?php

namespace App\AccessAudit\Application\Command\CreateAppRole;

final readonly class CreateAppRoleCommand
{
    /** @param list<string>|null $permissionCodes */
    public function __construct(
        public string $code,
        public string $libelle,
        public ?array $permissionCodes = null,
    ) {
    }
}

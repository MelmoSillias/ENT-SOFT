<?php

namespace App\AccessAudit\Application\Command\DeleteAppRole;

final readonly class DeleteAppRoleCommand
{
    public function __construct(public string $id)
    {
    }
}

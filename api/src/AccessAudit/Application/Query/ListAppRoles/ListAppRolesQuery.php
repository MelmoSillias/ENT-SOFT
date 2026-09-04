<?php

namespace App\AccessAudit\Application\Query\ListAppRoles;

final readonly class ListAppRolesQuery
{
    public function __construct(public bool $enabledOnly = false)
    {
    }
}

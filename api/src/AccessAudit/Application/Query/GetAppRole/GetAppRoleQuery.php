<?php

namespace App\AccessAudit\Application\Query\GetAppRole;

final readonly class GetAppRoleQuery
{
    public function __construct(public string $id)
    {
    }
}

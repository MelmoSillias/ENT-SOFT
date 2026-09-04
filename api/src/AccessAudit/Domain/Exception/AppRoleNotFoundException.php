<?php

namespace App\AccessAudit\Domain\Exception;

final class AppRoleNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Rôle introuvable : %s', $id));
    }
}

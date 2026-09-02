<?php

namespace App\IdentityAccess\Domain\Enum;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case COORDINATEUR = 'COORDINATEUR';
    case AGENT = 'AGENT';
}

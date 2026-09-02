<?php

namespace App\Document\Domain\Enum;

enum DocumentOwnerType: string
{
    case CLIENT = 'client';
    case PROJECT = 'project';
    case SITE = 'site';
}

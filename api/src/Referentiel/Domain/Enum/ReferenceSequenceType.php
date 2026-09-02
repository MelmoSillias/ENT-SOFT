<?php

namespace App\Referentiel\Domain\Enum;

enum ReferenceSequenceType: string
{
    case CLIENT = 'CLIENT';
    case PROJECT = 'PROJECT';
    case SITE = 'SITE';
    case EQUIPMENT = 'EQUIPMENT';
    case INVOICE = 'INVOICE';

    public function settingPrefixKey(): string
    {
        return match ($this) {
            self::CLIENT => 'REFERENCE_CLIENT',
            self::PROJECT => 'REFERENCE_PROJECT',
            self::SITE => 'REFERENCE_SITE',
            self::EQUIPMENT => 'REFERENCE_EQUIPMENT',
            self::INVOICE => 'REFERENCE_INVOICE',
        };
    }
}

<?php

namespace App\Referentiel\Domain\Enum;

enum ModeArrondi: string
{
    case HALF_UP = 'HALF_UP';
    case HALF_DOWN = 'HALF_DOWN';
    case HALF_EVEN = 'HALF_EVEN';
    case UP = 'UP';
    case DOWN = 'DOWN';
    case UNITE = 'UNITE';
}

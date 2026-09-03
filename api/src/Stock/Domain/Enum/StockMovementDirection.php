<?php

namespace App\Stock\Domain\Enum;

enum StockMovementDirection: string
{
    case IN = 'in';
    case OUT = 'out';
}

<?php

namespace App\Configuration\Domain\Enum;

enum TypeValeur: string
{
    case STRING = 'STRING';
    case DECIMAL = 'DECIMAL';
    case INTEGER = 'INTEGER';
    case BOOLEAN = 'BOOLEAN';
}

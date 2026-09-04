<?php

namespace App\Stock\Domain\Enum;

enum EquipmentUnit: string
{
    case UNIT = 'unit';
    case LOT = 'lot';
    case METER = 'meter';
    case SQUARE_METER = 'm2';
    case CUBIC_METER = 'm3';
    case KILOGRAM = 'kg';
    case LITER = 'liter';
    case PIECE = 'piece';
    case BOX = 'box';
    case SET = 'set';
    case HOUR = 'hour';
    case DAY = 'day';

    public function label(): string
    {
        return match ($this) {
            self::UNIT => 'Unité',
            self::LOT => 'Lot',
            self::METER => 'Mètre',
            self::SQUARE_METER => 'm²',
            self::CUBIC_METER => 'm³',
            self::KILOGRAM => 'Kilogramme',
            self::LITER => 'Litre',
            self::PIECE => 'Pièce',
            self::BOX => 'Boîte',
            self::SET => 'Ensemble',
            self::HOUR => 'Heure',
            self::DAY => 'Jour',
        };
    }
}

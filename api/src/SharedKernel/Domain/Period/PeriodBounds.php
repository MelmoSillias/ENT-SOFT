<?php

namespace App\SharedKernel\Domain\Period;

/**
 * Bornes d'un filtre de période.
 *
 * - from / to : journée entière (00:00:00 → 23:59:59), pour les colonnes date.
 * - instant : conserve l'heure choisie (filtres datetime, ordre début → fin).
 */
final class PeriodBounds
{
    public static function from(?string $value): ?\DateTimeImmutable
    {
        return self::parse($value)?->setTime(0, 0, 0);
    }

    public static function to(?string $value): ?\DateTimeImmutable
    {
        return self::parse($value)?->setTime(23, 59, 59);
    }

    /** Conserve l'instant choisi (date + heure). */
    public static function instant(?string $value): ?\DateTimeImmutable
    {
        return self::parse($value);
    }

    private static function parse(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new \DateTimeImmutable($value);
    }
}

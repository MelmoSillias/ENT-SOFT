<?php

namespace App\SharedKernel\Domain\Period;

/**
 * Normalise les bornes d'un filtre de période : début à 00:00:00, fin à 23:59:59.
 */
final class PeriodBounds
{
    public static function from(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable($value))->setTime(0, 0, 0);
    }

    public static function to(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new \DateTimeImmutable($value))->setTime(23, 59, 59);
    }
}

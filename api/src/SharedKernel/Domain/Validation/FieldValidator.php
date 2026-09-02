<?php

namespace App\SharedKernel\Domain\Validation;

final class FieldValidator
{
    public static function requireNonEmpty(string $value, string $label): string
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            throw new \InvalidArgumentException(sprintf('Le champ « %s » est obligatoire.', $label));
        }

        return $trimmed;
    }

    public static function normalizePhoneDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * Optional leading '+', digits, and single spaces (never leading, after '+', or doubled).
     * At least 6 digits; max 20 characters.
     */
    public static function requirePhone(string $value, string $label = 'Téléphone'): string
    {
        $trimmed = rtrim(self::requireNonEmpty($value, $label));

        if (!preg_match('/^(?:\+[0-9]+|[0-9]+)(?: [0-9]+)*$/', $trimmed)) {
            throw new \InvalidArgumentException(sprintf(
                'Le %s est invalide (« + » optionnel en début, chiffres et espaces simples).',
                strtolower($label),
            ));
        }

        $digits = self::normalizePhoneDigits($trimmed);
        if (strlen($digits) < 6) {
            throw new \InvalidArgumentException(sprintf(
                'Le %s est invalide (6 chiffres minimum).',
                strtolower($label),
            ));
        }

        if (strlen($trimmed) > 20) {
            throw new \InvalidArgumentException(sprintf(
                'Le %s est trop long (20 caractères maximum).',
                strtolower($label),
            ));
        }

        return $trimmed;
    }

    public static function requireMinLength(string $value, int $min, string $label): string
    {
        $trimmed = self::requireNonEmpty($value, $label);
        if (strlen($trimmed) < $min) {
            throw new \InvalidArgumentException(sprintf('Le champ « %s » doit contenir au moins %d caractères.', $label, $min));
        }

        return $trimmed;
    }
}

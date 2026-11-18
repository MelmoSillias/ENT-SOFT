<?php

namespace App\Finance\Application\Service;

final class InvoiceVatValidator
{
    public static function validate(mixed $enabled, mixed $rate): void
    {
        if (!is_bool($enabled)) {
            throw new \InvalidArgumentException('vatEnabled doit être un booléen.');
        }
        if ((!is_int($rate) && !is_float($rate)) || !is_finite((float) $rate) || $rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('vatRate doit être un nombre compris entre 0 et 100.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function validatePayload(array $data): void
    {
        self::validate(
            array_key_exists('vatEnabled', $data) ? $data['vatEnabled'] : false,
            array_key_exists('vatRate', $data) ? $data['vatRate'] : 18,
        );
    }
}

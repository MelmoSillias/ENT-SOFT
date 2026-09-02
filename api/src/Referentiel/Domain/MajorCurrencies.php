<?php

namespace App\Referentiel\Domain;

/**
 * Les devises les plus couramment suivies (ISO 4217).
 * XOF et XAF sont distincts (UEMOA vs CEMAC) — les deux sont inclus.
 */
final class MajorCurrencies
{
    /** @var list<string> */
    public const CODES = [
        'EUR', 'USD', 'XOF', 'XAF', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'CNY', 'HKD', 'NZD',
        'SEK', 'NOK', 'DKK', 'SGD', 'KRW', 'TRY', 'INR', 'BRL', 'ZAR', 'MXN',
    ];

    public const DEFAULT_COUNT = 22;

    /** @return list<string> */
    public static function forBase(string $base, int $limit = self::DEFAULT_COUNT): array
    {
        $base = strtoupper($base);

        $quotes = array_values(array_filter(
            self::CODES,
            static fn (string $code) => $code !== $base,
        ));

        return array_slice(array_values(array_unique($quotes)), 0, $limit);
    }
}

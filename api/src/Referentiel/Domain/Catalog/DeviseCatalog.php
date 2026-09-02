<?php

namespace App\Referentiel\Domain\Catalog;

use App\Referentiel\Domain\Enum\ModeArrondi;

/**
 * Catalogue hardcodé des devises avec règles d'arrondi ISO 4217.
 *
 * @phpstan-type DeviseDef array{
 *     nom: string,
 *     code: string,
 *     mode_arrondi: ModeArrondi,
 *     decimales: int,
 *     symbole: string|null,
 *     unite_arrondi: string|null
 * }
 */
final class DeviseCatalog
{
    /** @var list<DeviseDef>|null */
    private static ?array $cache = null;

    /** @return list<DeviseDef> */
    public static function all(): array
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        /** @var list<array{nom: string, code: string, mode_arrondi: string, decimales: int, symbole: string|null, unite_arrondi: string|null}> $raw */
        $raw = require \dirname(__DIR__, 4).'/resources/referentiel/devises.php';

        self::$cache = array_map(
            static fn (array $def): array => [
                'nom' => $def['nom'],
                'code' => $def['code'],
                'mode_arrondi' => ModeArrondi::from($def['mode_arrondi']),
                'decimales' => $def['decimales'],
                'symbole' => $def['symbole'],
                'unite_arrondi' => $def['unite_arrondi'],
            ],
            $raw,
        );

        return self::$cache;
    }

    public static function findByCode(string $code): ?array
    {
        $code = strtoupper($code);
        foreach (self::all() as $def) {
            if ($def['code'] === $code) {
                return $def;
            }
        }

        return null;
    }
}

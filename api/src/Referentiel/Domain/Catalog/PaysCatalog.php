<?php

namespace App\Referentiel\Domain\Catalog;

/**
 * Catalogue hardcodé des pays (ISO 3166-1 alpha-2).
 * Seule la Chine possède une liaison devise seedée par défaut au bootstrap.
 *
 * @phpstan-type DeviseSupporteeDef array{devise_code: string, taux_defaut: string, is_defaut: bool}
 * @phpstan-type PaysDef array{
 *     nom: string,
 *     code: string,
 *     indicatif_telephonique: string|null,
 *     devise_defaut_code: string,
 *     devises_supportees: list<DeviseSupporteeDef>
 * }
 */
final class PaysCatalog
{
    /** @var list<PaysDef>|null */
    private static ?array $cache = null;

    /** @return list<PaysDef> */
    public static function all(): array
    {
        if (null !== self::$cache) {
            return self::$cache;
        }

        /** @var list<PaysDef> $raw */
        $raw = require \dirname(__DIR__, 4).'/resources/referentiel/pays.php';
        self::$cache = $raw;

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

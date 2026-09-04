<?php

namespace App\Impression\Application\Service;

/**
 * Convertit un montant en lettres françaises (style facture ENT).
 */
final class AmountInWordsFrench
{
    public static function format(float|int $amount, string $currencyLabel = 'Franc CFA'): string
    {
        $rounded = (int) round($amount);
        $formattedNumber = number_format($rounded, 0, ',', ' ');

        $words = self::spellOut($rounded);
        $words = mb_convert_case($words, MB_CASE_TITLE, 'UTF-8');
        $words = str_replace('-', ' ', $words);

        return sprintf(
            'Arrêté la présente facture à la somme de : %s ( %s ) %s',
            $words,
            $formattedNumber,
            $currencyLabel
        );
    }

    private static function spellOut(int $amount): string
    {
        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
            $result = $formatter->format($amount);
            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        return self::fallbackSpellOut($amount);
    }

    private static function fallbackSpellOut(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }

        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];

        if ($n < 0) {
            return 'moins '.self::fallbackSpellOut(abs($n));
        }
        if ($n < 20) {
            return $units[$n];
        }
        if ($n < 100) {
            $t = intdiv($n, 10);
            $u = $n % 10;
            if ($t === 7 || $t === 9) {
                return $tens[$t].($u === 0 && $t === 8 ? 's' : '-'.self::fallbackSpellOut(10 + $u));
            }
            if ($t === 8) {
                return $tens[$t].($u === 0 ? 's' : '-'.$units[$u]);
            }
            if ($u === 1 && $t !== 8) {
                return $tens[$t].' et un';
            }

            return $tens[$t].($u ? '-'.$units[$u] : '');
        }
        if ($n < 1000) {
            $h = intdiv($n, 100);
            $r = $n % 100;
            $prefix = $h === 1 ? 'cent' : $units[$h].' cent';
            if ($r === 0 && $h > 1) {
                return $prefix.'s';
            }

            return $prefix.($r ? ' '.self::fallbackSpellOut($r) : '');
        }
        if ($n < 1000000) {
            $th = intdiv($n, 1000);
            $r = $n % 1000;
            $prefix = $th === 1 ? 'mille' : self::fallbackSpellOut($th).' mille';

            return $prefix.($r ? ' '.self::fallbackSpellOut($r) : '');
        }

        $m = intdiv($n, 1000000);
        $r = $n % 1000000;
        $prefix = $m === 1 ? 'un million' : self::fallbackSpellOut($m).' millions';

        return $prefix.($r ? ' '.self::fallbackSpellOut($r) : '');
    }
}

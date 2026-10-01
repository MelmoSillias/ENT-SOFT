<?php

namespace App\SharedKernel\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;

/**
 * Filtres de période inclusifs (bornes from/to incluses).
 *
 * Sous SQLite, comparer une colonne DATE (stockée 'Y-m-d') à un paramètre
 * DATETIME ('Y-m-d H:i:s') exclut la borne basse : '2026-03-01' >= '2026-03-01 00:00:00' est faux.
 * Il faut donc binder les bornes en DATE_IMMUTABLE pour les colonnes date.
 */
final class PeriodQueryParameter
{
    public static function applyDate(
        QueryBuilder $qb,
        string $field,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        string $fromParam = 'from',
        string $toParam = 'to',
    ): void {
        if ($from !== null) {
            $qb->andWhere(sprintf('%s >= :%s', $field, $fromParam))
                ->setParameter($fromParam, $from, Types::DATE_IMMUTABLE);
        }
        if ($to !== null) {
            $qb->andWhere(sprintf('%s <= :%s', $field, $toParam))
                ->setParameter($toParam, $to, Types::DATE_IMMUTABLE);
        }
    }

    public static function applyDateTime(
        QueryBuilder $qb,
        string $field,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        string $fromParam = 'from',
        string $toParam = 'to',
    ): void {
        if ($from !== null) {
            $qb->andWhere(sprintf('%s >= :%s', $field, $fromParam))
                ->setParameter($fromParam, $from);
        }
        if ($to !== null) {
            $qb->andWhere(sprintf('%s <= :%s', $field, $toParam))
                ->setParameter($toParam, $to);
        }
    }
}

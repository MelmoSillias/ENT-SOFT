<?php

namespace App\SharedKernel\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Liaison UUID compatible SQLite.
 *
 * Sous SQLite, Doctrine stocke les UUID en TEXT (octets binaires).
 * Les anciens seeds en BLOB sont normalisés par Version20260904160000 ;
 * le dual match reste une sécurité si des BLOB réapparaissent.
 */
final class UuidQueryParameter
{
    public static function eq(QueryBuilder $qb, string $field, string $param, Uuid $uuid): void
    {
        if (self::isSqlite($qb)) {
            $qb->andWhere($qb->expr()->orX(
                sprintf('%s = :%s_blob', $field, $param),
                sprintf('%s = :%s_text', $field, $param),
            ));
            $bin = $uuid->toBinary();
            $qb->setParameter($param.'_blob', $bin, ParameterType::BINARY);
            $qb->setParameter($param.'_text', $bin);

            return;
        }

        $qb->andWhere(sprintf('%s = :%s', $field, $param));
        $qb->setParameter($param, $uuid, 'uuid');
    }

    public static function neq(QueryBuilder $qb, string $field, string $param, Uuid $uuid): void
    {
        if (self::isSqlite($qb)) {
            $qb->andWhere($qb->expr()->andX(
                sprintf('%s != :%s_blob', $field, $param),
                sprintf('%s != :%s_text', $field, $param),
            ));
            $bin = $uuid->toBinary();
            $qb->setParameter($param.'_blob', $bin, ParameterType::BINARY);
            $qb->setParameter($param.'_text', $bin);

            return;
        }

        $qb->andWhere(sprintf('%s != :%s', $field, $param));
        $qb->setParameter($param, $uuid, 'uuid');
    }

    /** @param list<Uuid> $uuids */
    public static function in(QueryBuilder $qb, string $field, string $param, array $uuids): void
    {
        if ($uuids === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        $bins = array_map(static fn (Uuid $id): string => $id->toBinary(), $uuids);

        if (self::isSqlite($qb)) {
            $qb->andWhere($qb->expr()->orX(
                sprintf('%s IN (:%s_blob)', $field, $param),
                sprintf('%s IN (:%s_text)', $field, $param),
            ));
            $qb->setParameter($param.'_blob', $bins, ArrayParameterType::BINARY);
            $qb->setParameter($param.'_text', $bins, ArrayParameterType::STRING);

            return;
        }

        $qb->andWhere(sprintf('%s IN (:%s)', $field, $param));
        $qb->setParameter($param, $uuids);
    }

    private static function isSqlite(QueryBuilder $qb): bool
    {
        $platform = $qb->getEntityManager()->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform || $platform instanceof AbstractMySQLPlatform) {
            return false;
        }

        $name = method_exists($platform, 'getName') ? (string) $platform->getName() : '';
        $class = $platform::class;

        return str_contains(strtolower($class), 'sqlite') || strtolower($name) === 'sqlite';
    }
}

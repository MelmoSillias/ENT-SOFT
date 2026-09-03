<?php

namespace App\SharedKernel\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;

/**
 * Liaison UUID compatible SQLite (BLOB binaire).
 * Le type Doctrine "uuid" en paramètre DQL ne matche pas les BLOB seedés sous SQLite.
 */
final class UuidQueryParameter
{
    public static function bind(QueryBuilder $qb, string $name, Uuid $uuid): void
    {
        $qb->setParameter($name, $uuid->toBinary(), ParameterType::BINARY);
    }

    /** @param list<Uuid> $uuids */
    public static function bindList(QueryBuilder $qb, string $name, array $uuids): void
    {
        if ($uuids === []) {
            $qb->setParameter($name, []);

            return;
        }

        $qb->setParameter(
            $name,
            array_map(static fn (Uuid $id): string => $id->toBinary(), $uuids),
            ArrayParameterType::BINARY,
        );
    }
}

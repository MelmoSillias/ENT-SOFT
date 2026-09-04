<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalise les UUID seedés (BLOB) au format Doctrine (TEXT) sous SQLite.
 *
 * Sans cela, Doctrine flush() fait un UPDATE … WHERE id = ? en TEXT qui
 * ne matche aucune ligne BLOB : succès HTTP / toast, données inchangées.
 */
final class Version20260904160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SQLite: convertir les UUID BLOB (seeds) en TEXT (format Doctrine)';
    }

    public function up(Schema $schema): void
    {
        // Conversion faite en postUp (introspection + CAST).
    }

    public function postUp(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $name = method_exists($platform, 'getName') ? (string) $platform->getName() : '';
        $class = $platform::class;
        $isSqlite = str_contains(strtolower($class), 'sqlite') || strtolower($name) === 'sqlite';
        if (!$isSqlite) {
            return;
        }

        $tables = $this->connection->fetchFirstColumn(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT LIKE 'doctrine_%'"
        );

        $converted = 0;
        foreach ($tables as $table) {
            if (!preg_match('/^[a-z0-9_]+$/i', (string) $table)) {
                continue;
            }

            $columns = $this->connection->fetchAllAssociative(sprintf('PRAGMA table_info(%s)', $table));
            foreach ($columns as $column) {
                $col = (string) ($column['name'] ?? '');
                if ($col !== 'id' && !str_ends_with($col, '_id')) {
                    continue;
                }
                if (!preg_match('/^[a-z0-9_]+$/i', $col)) {
                    continue;
                }

                $blobCount = (int) $this->connection->fetchOne(
                    sprintf("SELECT COUNT(*) FROM %s WHERE %s IS NOT NULL AND typeof(%s) = 'blob'", $table, $col, $col)
                );
                if ($blobCount === 0) {
                    continue;
                }

                $this->connection->executeStatement(
                    sprintf("UPDATE %s SET %s = CAST(%s AS TEXT) WHERE typeof(%s) = 'blob'", $table, $col, $col, $col)
                );
                $converted += $blobCount;
                $this->write(sprintf('  %s.%s : %d UUID BLOB → TEXT', $table, $col, $blobCount));
            }
        }

        $this->write(sprintf('UUID normalisés : %d valeur(s)', $converted));
    }

    public function down(Schema $schema): void
    {
        // Irréversible volontairement (TEXT est le format Doctrine attendu).
    }
}

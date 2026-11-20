<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261120130000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ClientLogoMigrationTest extends TestCase
{
    public function testMigrationPreservesExistingClientsAndCanBeReverted(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261120130000.php';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        $schema->createTable('clients')->addColumn('id', 'string');
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->executeStatement($sql);
        }
        $connection->insert('clients', ['id' => 'existing-client']);
        $migration = new Version20261120130000($connection, new NullLogger());
        $before = clone $schema;
        $migration->up($schema);
        self::assertFalse($schema->getTable('clients')->getColumn('logo_url')->getNotnull());
        self::assertSame(255, $schema->getTable('clients')->getColumn('logo_url')->getLength());
        $this->apply($connection, $before, $schema);
        self::assertNull($connection->fetchOne('SELECT logo_url FROM clients'));
        $connection->update('clients', ['logo_url' => '/uploads/client-logos/example.png'], ['id' => 'existing-client']);
        $before = clone $schema;
        $migration->down($schema);
        $this->apply($connection, $before, $schema);
        self::assertSame('existing-client', $connection->fetchOne('SELECT id FROM clients'));
        self::assertArrayNotHasKey('logo_url', $connection->createSchemaManager()->listTableColumns('clients'));
        $connection->close();
    }

    private function apply(\Doctrine\DBAL\Connection $connection, Schema $before, Schema $after): void
    {
        $diff = $connection->createSchemaManager()->createComparator()->compareSchemas($before, $after);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) {
            $connection->executeStatement($sql);
        }
    }
}

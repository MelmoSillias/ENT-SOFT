<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use DoctrineMigrations\Version20261120120000;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final class ProjectDocumentMigrationTest extends TestCase
{
    public function testMigrationAddsAnIndexedExtensibleTableAndCanBeRolledBack(): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261120120000.php';
        if (!Type::hasType('uuid')) { Type::addType('uuid', UuidType::class); }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = new Schema();
        $projects = $schema->createTable('projects');
        $projects->addColumn('id', 'string');
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) { $connection->executeStatement($sql); }
        $connection->insert('projects', ['id' => 'existing-project']);
        $migration = new Version20261120120000($connection, new NullLogger());
        $before = clone $schema;
        $migration->up($schema);
        self::assertTrue($schema->getTable('project_documents')->hasIndex('idx_project_document_project'));
        self::assertSame('string', Type::lookupName($schema->getTable('project_documents')->getColumn('type')->getType()));
        $manager = $connection->createSchemaManager();
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        $connection->insert('project_documents', [
            'id' => Uuid::v4()->toRfc4122(), 'project_id' => Uuid::v4()->toRfc4122(),
            'client_id' => Uuid::v4()->toRfc4122(), 'type' => 'delivery_note', 'number' => 'BC-42',
            'date' => '2026-11-20', 'lines' => json_encode([['description' => 'Routeur', 'quantity' => 1, 'observation' => 'RAS']]),
            'created_at' => '2026-11-20 12:00:00', 'updated_at' => '2026-11-20 12:00:00',
        ]);
        self::assertSame('BC-42', $connection->fetchOne('SELECT number FROM project_documents'));
        require_once dirname(__DIR__, 2).'/migrations/Version20261120131000.php';
        $objectMigration = new \DoctrineMigrations\Version20261120131000($connection, new NullLogger());
        $before = clone $schema;
        $objectMigration->up($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        self::assertNull($connection->fetchOne('SELECT object FROM project_documents'));
        $connection->executeStatement('UPDATE project_documents SET object = ?', ['Livraison du site']);
        self::assertSame('Livraison du site', $connection->fetchOne('SELECT object FROM project_documents'));
        require_once dirname(__DIR__, 2).'/migrations/Version20261120132000.php';
        $nullableMigration = new \DoctrineMigrations\Version20261120132000($connection, new NullLogger());
        $before = clone $schema;
        $nullableMigration->up($schema);
        self::assertFalse($schema->getTable('project_documents')->getColumn('project_id')->getNotnull());
        self::assertTrue($schema->getTable('project_documents')->hasIndex('idx_project_document_project'));
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        self::assertSame('BC-42', $connection->fetchOne('SELECT number FROM project_documents'));
        $originalProjectId = $connection->fetchOne('SELECT project_id FROM project_documents');
        $connection->executeStatement('UPDATE project_documents SET project_id = NULL');
        self::assertNull($connection->fetchOne('SELECT project_id FROM project_documents'));
        try {
            $nullableMigration->down(clone $schema);
            self::fail('Rollback must refuse to lose standalone documents.');
        } catch (\Doctrine\Migrations\Exception\AbortMigration $e) {
            self::assertStringContainsString('Réaffecter', $e->getMessage());
        }
        $connection->executeStatement('UPDATE project_documents SET project_id = ?', [$originalProjectId]);
        $before = clone $schema;
        $nullableMigration->down($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        self::assertTrue($connection->createSchemaManager()->introspectTable('project_documents')->getColumn('project_id')->getNotnull());
        self::assertSame('Livraison du site', $connection->fetchOne('SELECT object FROM project_documents'));
        $before = clone $schema;
        $objectMigration->down($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        self::assertSame('BC-42', $connection->fetchOne('SELECT number FROM project_documents'));
        $before = clone $schema;
        $migration->down($schema);
        $diff = $manager->createComparator()->compareSchemas($before, $schema);
        foreach ($connection->getDatabasePlatform()->getAlterSchemaSQL($diff) as $sql) { $connection->executeStatement($sql); }
        self::assertFalse($connection->createSchemaManager()->tablesExist(['project_documents']));
        self::assertSame('existing-project', $connection->fetchOne('SELECT id FROM projects'));
        $connection->close();
    }
}

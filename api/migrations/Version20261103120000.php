<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Mémorisation des lignes importées pour empêcher les doublons'; }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('data_import_records');
        $table->addColumn('id', 'string', ['length' => 64]);
        $table->addColumn('fingerprint', 'string', ['length' => 64]);
        $table->addColumn('entity_id', 'string', ['length' => 36]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void { $schema->dropTable('data_import_records'); }
}

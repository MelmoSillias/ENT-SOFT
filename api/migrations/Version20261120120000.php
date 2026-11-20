<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documents métier de projet extensibles, dont les bons de livraison';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('project_documents');
        $table->addColumn('id', 'uuid');
        $table->addColumn('project_id', 'uuid');
        $table->addColumn('type', 'string', ['length' => 50]);
        $table->addColumn('number', 'string', ['length' => 255]);
        $table->addColumn('date', 'date_immutable');
        $table->addColumn('client_id', 'uuid');
        $table->addColumn('lines', 'json');
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['id']);
        $table->addIndex(['project_id'], 'idx_project_document_project');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('project_documents');
    }
}

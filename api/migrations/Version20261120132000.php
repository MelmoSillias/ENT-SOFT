<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autoriser les documents métier sans projet';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('project_documents')->getColumn('project_id')->setNotnull(false);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM project_documents WHERE project_id IS NULL') > 0,
            'Réaffecter ou supprimer les documents sans projet avant de rétablir project_id NOT NULL.',
        );
        $schema->getTable('project_documents')->getColumn('project_id')->setNotnull(true);
    }
}

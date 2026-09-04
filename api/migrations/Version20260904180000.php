<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unique (project_id, site_id) on project_sites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_project_site ON project_sites (project_id, site_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_project_site');
    }
}

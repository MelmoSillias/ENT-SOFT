<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planning de groupe : un document JSON par jour';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $platformName = $platform::class;

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE day_group_plannings (id BINARY(16) NOT NULL, plan_date DATE NOT NULL, payload JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_day_group_planning_date (plan_date), PRIMARY KEY(id))');

            return;
        }

        if (str_contains($platformName, 'PostgreSQL')) {
            $this->addSql('CREATE TABLE day_group_plannings (id UUID NOT NULL, plan_date DATE NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_day_group_planning_date ON day_group_plannings (plan_date)');

            return;
        }

        $this->addSql('CREATE TABLE day_group_plannings (id BLOB NOT NULL, plan_date DATE NOT NULL, payload CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_day_group_planning_date ON day_group_plannings (plan_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE day_group_plannings');
    }
}

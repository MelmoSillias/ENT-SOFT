<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rapports d’activité normalisés et lignes de sites/projets';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $platformName = $platform::class;

        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql("CREATE TABLE activity_reports (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', code VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, author_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_activity_report_code (code), INDEX idx_activity_report_period (start_date, end_date), PRIMARY KEY(id))");
            $this->addSql("CREATE TABLE activity_report_sites (id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', report_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', project_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', project_site_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', site_id BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)', project_code_snapshot VARCHAR(50) DEFAULT NULL, project_title_snapshot VARCHAR(255) DEFAULT NULL, site_code_snapshot VARCHAR(50) DEFAULT NULL, site_title_snapshot VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, observation LONGTEXT DEFAULT NULL, planned_task LONGTEXT DEFAULT NULL, group_label VARCHAR(255) DEFAULT NULL, attendees JSON NOT NULL, position INT NOT NULL, INDEX IDX_ACTIVITY_REPORT_SITE_REPORT (report_id), PRIMARY KEY(id))");
            $this->addSql('ALTER TABLE activity_report_sites ADD CONSTRAINT FK_ACTIVITY_REPORT_SITE_REPORT FOREIGN KEY (report_id) REFERENCES activity_reports (id) ON DELETE CASCADE');
            return;
        }

        if (str_contains($platformName, 'PostgreSQL')) {
            $this->addSql('CREATE TABLE activity_reports (id UUID NOT NULL, code VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, description TEXT NOT NULL, status VARCHAR(255) NOT NULL, author_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE UNIQUE INDEX uniq_activity_report_code ON activity_reports (code)');
            $this->addSql('CREATE INDEX idx_activity_report_period ON activity_reports (start_date, end_date)');
            $this->addSql('CREATE TABLE activity_report_sites (id UUID NOT NULL, report_id UUID NOT NULL, project_id UUID DEFAULT NULL, project_site_id UUID DEFAULT NULL, site_id UUID DEFAULT NULL, project_code_snapshot VARCHAR(50) DEFAULT NULL, project_title_snapshot VARCHAR(255) DEFAULT NULL, site_code_snapshot VARCHAR(50) DEFAULT NULL, site_title_snapshot VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, observation TEXT DEFAULT NULL, planned_task TEXT DEFAULT NULL, group_label VARCHAR(255) DEFAULT NULL, attendees JSON NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX IDX_ACTIVITY_REPORT_SITE_REPORT ON activity_report_sites (report_id)');
            $this->addSql('ALTER TABLE activity_report_sites ADD CONSTRAINT FK_ACTIVITY_REPORT_SITE_REPORT FOREIGN KEY (report_id) REFERENCES activity_reports (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            return;
        }

        $this->addSql('CREATE TABLE activity_reports (id BLOB NOT NULL, code VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, description CLOB NOT NULL, status VARCHAR(255) NOT NULL, author_id BLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id), CONSTRAINT uniq_activity_report_code UNIQUE (code))');
        $this->addSql('CREATE INDEX idx_activity_report_period ON activity_reports (start_date, end_date)');
        $this->addSql('CREATE TABLE activity_report_sites (id BLOB NOT NULL, report_id BLOB NOT NULL, project_id BLOB DEFAULT NULL, project_site_id BLOB DEFAULT NULL, site_id BLOB DEFAULT NULL, project_code_snapshot VARCHAR(50) DEFAULT NULL, project_title_snapshot VARCHAR(255) DEFAULT NULL, site_code_snapshot VARCHAR(50) DEFAULT NULL, site_title_snapshot VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, observation CLOB DEFAULT NULL, planned_task CLOB DEFAULT NULL, group_label VARCHAR(255) DEFAULT NULL, attendees CLOB NOT NULL, position INT NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_ACTIVITY_REPORT_SITE_REPORT FOREIGN KEY (report_id) REFERENCES activity_reports (id) ON DELETE CASCADE)');
        $this->addSql('CREATE INDEX IDX_ACTIVITY_REPORT_SITE_REPORT ON activity_report_sites (report_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activity_report_sites');
        $this->addSql('DROP TABLE activity_reports');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table employee_positions — suivi des check-ins GPS (10 dernières par employé)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE employee_positions (
            employee_id BLOB NOT NULL,
            latitude DOUBLE PRECISION NOT NULL,
            longitude DOUBLE PRECISION NOT NULL,
            recorded_at DATETIME NOT NULL,
            accuracy DOUBLE PRECISION DEFAULT NULL,
            id BLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_employee_positions_employee ON employee_positions (employee_id)');
        $this->addSql('CREATE INDEX idx_employee_positions_recorded ON employee_positions (recorded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE employee_positions');
    }
}

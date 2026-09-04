<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move unit from stock_movements to equipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE equipment ADD COLUMN unit VARCHAR(50) DEFAULT 'unit' NOT NULL");

        // SQLite: rebuild stock_movements without unit
        $this->addSql('CREATE TABLE stock_movements_new (
            date DATE NOT NULL,
            quantity DOUBLE PRECISION NOT NULL,
            direction VARCHAR(255) DEFAULT \'in\' NOT NULL,
            client_id BLOB DEFAULT NULL,
            project_id BLOB DEFAULT NULL,
            site_id BLOB DEFAULT NULL,
            id BLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_enabled BOOLEAN DEFAULT 1 NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('INSERT INTO stock_movements_new (date, quantity, direction, client_id, project_id, site_id, id, created_at, updated_at, is_enabled)
            SELECT date, quantity, direction, client_id, project_id, site_id, id, created_at, updated_at, is_enabled FROM stock_movements');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('ALTER TABLE stock_movements_new RENAME TO stock_movements');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE stock_movements ADD COLUMN unit VARCHAR(50) DEFAULT 'u' NOT NULL");

        $this->addSql('CREATE TABLE equipment_new (
            code VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            client_id BLOB DEFAULT NULL,
            id BLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_enabled BOOLEAN DEFAULT 1 NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('INSERT INTO equipment_new (code, title, description, client_id, id, created_at, updated_at, is_enabled)
            SELECT code, title, description, client_id, id, created_at, updated_at, is_enabled FROM equipment');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('ALTER TABLE equipment_new RENAME TO equipment');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipment_code ON equipment (code)');
    }
}

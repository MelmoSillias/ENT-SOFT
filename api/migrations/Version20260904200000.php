<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260904200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'App roles, employees prenom/nom/role_code, prestataires/prestations, financial_transactions without project_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS app_roles (code VARCHAR(50) NOT NULL, libelle VARCHAR(100) NOT NULL, is_system BOOLEAN DEFAULT 0 NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uniq_app_role_code ON app_roles (code)');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $roles = [
            ['ADMIN', 'Administrateur', 1],
            ['COORDINATEUR', 'Coordinateur', 1],
            ['AGENT', 'Agent', 1],
            ['TECHNICIEN', 'Technicien', 0],
            ['SECRETAIRE', 'Secrétaire', 0],
            ['COMPTABLE', 'Comptable', 0],
            ['GERANT', 'Gérant', 0],
        ];
        foreach ($roles as [$code, $libelle, $isSystem]) {
            $id = Uuid::v7()->toBinary();
            $this->addSql(
                "INSERT OR IGNORE INTO app_roles (id, code, libelle, is_system, created_at, updated_at, is_enabled)
                 SELECT ?, ?, ?, ?, ?, ?, 1 WHERE NOT EXISTS (SELECT 1 FROM app_roles WHERE code = ?)",
                [$id, $code, $libelle, $isSystem, $now, $now, $code],
            );
        }

        // Rebuild employees with prenom/nom/role_code
        $this->addSql('CREATE TABLE employees_new (prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, address CLOB DEFAULT NULL, role_code VARCHAR(50) NOT NULL, user_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql("INSERT INTO employees_new (prenom, nom, email, phone, address, role_code, user_id, id, created_at, updated_at, is_enabled)
            SELECT '', COALESCE(name, ''), email, phone, address, COALESCE(\"function\", 'AGENT'), user_id, id, created_at, updated_at, is_enabled FROM employees");
        $this->addSql('DROP TABLE employees');
        $this->addSql('ALTER TABLE employees_new RENAME TO employees');

        $this->addSql('CREATE TABLE IF NOT EXISTS prestataires (prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, address CLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE IF NOT EXISTS prestations (prestataire_id BLOB NOT NULL, description CLOB NOT NULL, site_id BLOB DEFAULT NULL, amount DOUBLE PRECISION NOT NULL, work_status VARCHAR(30) NOT NULL, payment_status VARCHAR(30) NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_prestation_prestataire ON prestations (prestataire_id)');

        // Rebuild financial_transactions: drop project_id, nullable parties, add prestation_id
        $this->addSql('CREATE TABLE financial_transactions_new (
            date DATE NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            type VARCHAR(255) NOT NULL,
            category VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            status VARCHAR(255) NOT NULL,
            from_party VARCHAR(255) DEFAULT NULL,
            to_party VARCHAR(255) DEFAULT NULL,
            client_id BLOB DEFAULT NULL,
            site_id BLOB DEFAULT NULL,
            invoice_id BLOB DEFAULT NULL,
            prestation_id BLOB DEFAULT NULL,
            id BLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_enabled BOOLEAN DEFAULT 1 NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('INSERT INTO financial_transactions_new (date, amount, type, category, description, status, from_party, to_party, client_id, site_id, invoice_id, prestation_id, id, created_at, updated_at, is_enabled)
            SELECT date, amount, type, category, description, status, from_party, to_party, client_id, site_id, invoice_id, NULL, id, created_at, updated_at, is_enabled FROM financial_transactions');
        $this->addSql('DROP TABLE financial_transactions');
        $this->addSql('ALTER TABLE financial_transactions_new RENAME TO financial_transactions');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS prestations');
        $this->addSql('DROP TABLE IF EXISTS prestataires');
        $this->addSql('DROP TABLE IF EXISTS app_roles');
    }
}

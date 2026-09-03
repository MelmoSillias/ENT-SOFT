<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial ENT-SOFT (SQLite).
 */
final class Version20260903122230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial ENT-SOFT (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE client_comments (client_id BLOB NOT NULL, content CLOB NOT NULL, created_at DATETIME NOT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE client_contacts (client_id BLOB NOT NULL, name VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE clients (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_client_code ON clients (code)');
        $this->addSql('CREATE TABLE devises (nom VARCHAR(100) NOT NULL, code VARCHAR(3) NOT NULL, symbole VARCHAR(10) DEFAULT NULL, decimales INTEGER NOT NULL, mode_arrondi VARCHAR(255) NOT NULL, unite_arrondi NUMERIC(19, 6) DEFAULT NULL, is_actif BOOLEAN DEFAULT 1 NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_devise_code ON devises (code)');
        $this->addSql('CREATE TABLE documents (title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, owner_type VARCHAR(255) NOT NULL, owner_id BLOB NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE employees (name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, address CLOB DEFAULT NULL, "function" VARCHAR(100) NOT NULL, user_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE equipment (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, client_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_equipment_code ON equipment (code)');
        $this->addSql('CREATE TABLE financial_transactions (date DATE NOT NULL, amount DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, from_party VARCHAR(255) NOT NULL, to_party VARCHAR(255) NOT NULL, client_id BLOB DEFAULT NULL, project_id BLOB DEFAULT NULL, site_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE historique_actions ("action" VARCHAR(100) NOT NULL, description VARCHAR(1000) DEFAULT NULL, utilisateur_id BLOB NOT NULL, date_action DATETIME NOT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_historique_action_utilisateur ON historique_actions (utilisateur_id)');
        $this->addSql('CREATE INDEX idx_historique_action_date ON historique_actions (date_action)');
        $this->addSql('CREATE TABLE historique_settings (setting_cle VARCHAR(100) NOT NULL, ancienne_valeur VARCHAR(500) NOT NULL, nouvelle_valeur VARCHAR(500) NOT NULL, utilisateur_id BLOB NOT NULL, date_modification DATETIME NOT NULL, motif VARCHAR(500) DEFAULT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_historique_setting_cle ON historique_settings (setting_cle)');
        $this->addSql('CREATE TABLE historique_taux (liaison_id BLOB NOT NULL, pays_code VARCHAR(2) NOT NULL, pays_nom VARCHAR(100) NOT NULL, devise_code VARCHAR(3) NOT NULL, devise_symbole VARCHAR(10) DEFAULT NULL, ancien_taux NUMERIC(19, 6) DEFAULT NULL, nouveau_taux NUMERIC(19, 6) NOT NULL, utilisateur_id BLOB NOT NULL, date_modification DATETIME NOT NULL, motif VARCHAR(500) DEFAULT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_historique_taux_liaison ON historique_taux (liaison_id)');
        $this->addSql('CREATE INDEX idx_historique_taux_date ON historique_taux (date_modification)');
        $this->addSql('CREATE TABLE invoices (number VARCHAR(50) NOT NULL, date DATE NOT NULL, amount DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, client_id BLOB NOT NULL, project_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_invoice_number ON invoices (number)');
        $this->addSql('CREATE TABLE pays (nom VARCHAR(100) NOT NULL, code VARCHAR(2) NOT NULL, indicatif_telephonique VARCHAR(10) DEFAULT NULL, is_actif BOOLEAN DEFAULT 1 NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, devise_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_349F3CAEF4445056 FOREIGN KEY (devise_id) REFERENCES devises (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_349F3CAEF4445056 ON pays (devise_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pays_code ON pays (code)');
        $this->addSql('CREATE TABLE pays_devise_liaisons (taux_defaut NUMERIC(19, 6) NOT NULL, is_defaut BOOLEAN DEFAULT 0 NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, pays_id BLOB NOT NULL, devise_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_682FBD79A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_682FBD79F4445056 FOREIGN KEY (devise_id) REFERENCES devises (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_682FBD79A6E44244 ON pays_devise_liaisons (pays_id)');
        $this->addSql('CREATE INDEX IDX_682FBD79F4445056 ON pays_devise_liaisons (devise_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_pays_devise ON pays_devise_liaisons (pays_id, devise_id)');
        $this->addSql('CREATE TABLE permissions (code VARCHAR(100) NOT NULL, libelle VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, module VARCHAR(50) NOT NULL, id BLOB NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_permission_code ON permissions (code)');
        $this->addSql('CREATE TABLE project_events (project_id BLOB NOT NULL, date DATE NOT NULL, title VARCHAR(255) NOT NULL, id BLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE project_lots (project_id BLOB NOT NULL, code VARCHAR(50) NOT NULL, title VARCHAR(255) DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_lot_code ON project_lots (project_id, code)');
        $this->addSql('CREATE TABLE project_sites (project_id BLOB NOT NULL, site_id BLOB NOT NULL, lot_id BLOB DEFAULT NULL, technician_id BLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, date_added DATETIME NOT NULL, informations_values CLOB NOT NULL, employee_ids CLOB NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE projects (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, object CLOB DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, budget DOUBLE PRECISION NOT NULL, client_id BLOB NOT NULL, sites_informations CLOB NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_code ON projects (code)');
        $this->addSql('CREATE TABLE reference_sequences (type VARCHAR(32) NOT NULL, derniere_valeur INTEGER NOT NULL, PRIMARY KEY (type))');
        $this->addSql('CREATE TABLE refresh_tokens (utilisateur_id BLOB NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1B3BC57DA ON refresh_tokens (token_hash)');
        $this->addSql('CREATE TABLE role_permissions (role VARCHAR(255) NOT NULL, id BLOB NOT NULL, permission_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_1FBA94E6FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1FBA94E6FED90CCA ON role_permissions (permission_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_role_permission ON role_permissions (role, permission_id)');
        $this->addSql('CREATE TABLE settings (cle VARCHAR(100) NOT NULL, valeur VARCHAR(500) NOT NULL, type VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (cle))');
        $this->addSql('CREATE TABLE sites (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, client_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_site_code ON sites (code)');
        // Parent avant enfant (FK SQLite)
        $this->addSql('CREATE TABLE stock_movements (date DATE NOT NULL, quantity DOUBLE PRECISION NOT NULL, unit VARCHAR(50) NOT NULL, client_id BLOB DEFAULT NULL, project_id BLOB DEFAULT NULL, site_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE stock_movement_lines (equipment_id BLOB NOT NULL, quantity DOUBLE PRECISION NOT NULL, id BLOB NOT NULL, movement_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_1558E2C8229E70A7 FOREIGN KEY (movement_id) REFERENCES stock_movements (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1558E2C8229E70A7 ON stock_movement_lines (movement_id)');
        $this->addSql('CREATE TABLE tasks (title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, date_creation DATETIME NOT NULL, date_due DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, site_id BLOB NOT NULL, employee_id BLOB DEFAULT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE utilisateur_permissions (utilisateur_id BLOB NOT NULL, accorde BOOLEAN NOT NULL, attribue_par_id BLOB NOT NULL, date_attribution DATETIME NOT NULL, id BLOB NOT NULL, permission_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_D90BC1FCFED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D90BC1FCFED90CCA ON utilisateur_permissions (permission_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateur_permission ON utilisateur_permissions (utilisateur_id, permission_id)');
        $this->addSql('CREATE TABLE utilisateurs (prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, login VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateur_login ON utilisateurs (login)');
        $this->addSql('CREATE UNIQUE INDEX uniq_utilisateur_telephone ON utilisateurs (telephone)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_movement_lines');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE client_comments');
        $this->addSql('DROP TABLE client_contacts');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE devises');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE financial_transactions');
        $this->addSql('DROP TABLE historique_actions');
        $this->addSql('DROP TABLE historique_settings');
        $this->addSql('DROP TABLE historique_taux');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE pays');
        $this->addSql('DROP TABLE pays_devise_liaisons');
        $this->addSql('DROP TABLE permissions');
        $this->addSql('DROP TABLE project_events');
        $this->addSql('DROP TABLE project_lots');
        $this->addSql('DROP TABLE project_sites');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE reference_sequences');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE sites');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE utilisateur_permissions');
        $this->addSql('DROP TABLE utilisateurs');
    }
}

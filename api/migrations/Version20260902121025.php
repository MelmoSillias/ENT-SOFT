<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial ENT-SOFT (base vide).
 */
final class Version20260902121025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial ENT-SOFT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_tokens (utilisateur_id BINARY(16) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9BACE7E1B3BC57DA (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateurs (prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, login VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_utilisateur_login (login), UNIQUE INDEX uniq_utilisateur_telephone (telephone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE clients (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_client_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE client_comments (client_id BINARY(16) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, id BINARY(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sites (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, client_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_site_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE projects (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, object LONGTEXT DEFAULT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, budget DOUBLE PRECISION NOT NULL, client_id BINARY(16) NOT NULL, sites_informations JSON NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_project_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_events (project_id BINARY(16) NOT NULL, date DATE NOT NULL, title VARCHAR(255) NOT NULL, id BINARY(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE project_sites (project_id BINARY(16) NOT NULL, site_id BINARY(16) NOT NULL, status VARCHAR(255) NOT NULL, date_added DATETIME NOT NULL, informations_values JSON NOT NULL, employee_ids JSON NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE employees (name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, address LONGTEXT DEFAULT NULL, function VARCHAR(100) NOT NULL, user_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tasks (title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, date_due DATE DEFAULT NULL, status VARCHAR(255) NOT NULL, site_id BINARY(16) NOT NULL, employee_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE financial_transactions (date DATE NOT NULL, amount DOUBLE PRECISION NOT NULL, type VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, from_party VARCHAR(255) NOT NULL, to_party VARCHAR(255) NOT NULL, client_id BINARY(16) DEFAULT NULL, project_id BINARY(16) DEFAULT NULL, site_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invoices (number VARCHAR(50) NOT NULL, date DATE NOT NULL, amount DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, client_id BINARY(16) NOT NULL, project_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_invoice_number (number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE equipment (code VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, client_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_equipment_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_movements (date DATE NOT NULL, quantity DOUBLE PRECISION NOT NULL, unit VARCHAR(50) NOT NULL, client_id BINARY(16) DEFAULT NULL, project_id BINARY(16) DEFAULT NULL, site_id BINARY(16) DEFAULT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE stock_movement_lines (equipment_id BINARY(16) NOT NULL, quantity DOUBLE PRECISION NOT NULL, id BINARY(16) NOT NULL, movement_id BINARY(16) NOT NULL, INDEX IDX_1558E2C8229E70A7 (movement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE documents (title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL, owner_type VARCHAR(255) NOT NULL, owner_id BINARY(16) NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE historique_actions (action VARCHAR(100) NOT NULL, description VARCHAR(1000) DEFAULT NULL, utilisateur_id BINARY(16) NOT NULL, date_action DATETIME NOT NULL, id BINARY(16) NOT NULL, INDEX idx_historique_action_utilisateur (utilisateur_id), INDEX idx_historique_action_date (date_action), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE permissions (code VARCHAR(100) NOT NULL, libelle VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, module VARCHAR(50) NOT NULL, id BINARY(16) NOT NULL, is_enabled TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_permission_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role_permissions (role VARCHAR(255) NOT NULL, id BINARY(16) NOT NULL, permission_id BINARY(16) NOT NULL, INDEX IDX_1FBA94E6FED90CCA (permission_id), UNIQUE INDEX uniq_role_permission (role, permission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur_permissions (utilisateur_id BINARY(16) NOT NULL, accorde TINYINT NOT NULL, attribue_par_id BINARY(16) NOT NULL, date_attribution DATETIME NOT NULL, id BINARY(16) NOT NULL, permission_id BINARY(16) NOT NULL, INDEX IDX_D90BC1FCFED90CCA (permission_id), UNIQUE INDEX uniq_utilisateur_permission (utilisateur_id, permission_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE devises (nom VARCHAR(100) NOT NULL, code VARCHAR(3) NOT NULL, symbole VARCHAR(10) DEFAULT NULL, decimales INT NOT NULL, mode_arrondi VARCHAR(255) NOT NULL, unite_arrondi NUMERIC(19, 6) DEFAULT NULL, is_actif TINYINT DEFAULT 1 NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_devise_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE historique_taux (liaison_id BINARY(16) NOT NULL, pays_code VARCHAR(2) NOT NULL, pays_nom VARCHAR(100) NOT NULL, devise_code VARCHAR(3) NOT NULL, devise_symbole VARCHAR(10) DEFAULT NULL, ancien_taux NUMERIC(19, 6) DEFAULT NULL, nouveau_taux NUMERIC(19, 6) NOT NULL, utilisateur_id BINARY(16) NOT NULL, date_modification DATETIME NOT NULL, motif VARCHAR(500) DEFAULT NULL, id BINARY(16) NOT NULL, INDEX idx_historique_taux_liaison (liaison_id), INDEX idx_historique_taux_date (date_modification), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pays (nom VARCHAR(100) NOT NULL, code VARCHAR(2) NOT NULL, indicatif_telephonique VARCHAR(10) DEFAULT NULL, is_actif TINYINT DEFAULT 1 NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, devise_id BINARY(16) NOT NULL, INDEX IDX_349F3CAEF4445056 (devise_id), UNIQUE INDEX uniq_pays_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pays_devise_liaisons (taux_defaut NUMERIC(19, 6) NOT NULL, is_defaut TINYINT DEFAULT 0 NOT NULL, id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, pays_id BINARY(16) NOT NULL, devise_id BINARY(16) NOT NULL, INDEX IDX_682FBD79A6E44244 (pays_id), INDEX IDX_682FBD79F4445056 (devise_id), UNIQUE INDEX uniq_pays_devise (pays_id, devise_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reference_sequences (type VARCHAR(32) NOT NULL, derniere_valeur INT NOT NULL, PRIMARY KEY (type)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE historique_settings (setting_cle VARCHAR(100) NOT NULL, ancienne_valeur VARCHAR(500) NOT NULL, nouvelle_valeur VARCHAR(500) NOT NULL, utilisateur_id BINARY(16) NOT NULL, date_modification DATETIME NOT NULL, motif VARCHAR(500) DEFAULT NULL, id BINARY(16) NOT NULL, INDEX idx_historique_setting_cle (setting_cle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE settings (cle VARCHAR(100) NOT NULL, valeur VARCHAR(500) NOT NULL, type VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (cle)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE stock_movement_lines ADD CONSTRAINT FK_1558E2C8229E70A7 FOREIGN KEY (movement_id) REFERENCES stock_movements (id)');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT FK_1FBA94E6FED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE utilisateur_permissions ADD CONSTRAINT FK_D90BC1FCFED90CCA FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pays ADD CONSTRAINT FK_349F3CAEF4445056 FOREIGN KEY (devise_id) REFERENCES devises (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE pays_devise_liaisons ADD CONSTRAINT FK_682FBD79A6E44244 FOREIGN KEY (pays_id) REFERENCES pays (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pays_devise_liaisons ADD CONSTRAINT FK_682FBD79F4445056 FOREIGN KEY (devise_id) REFERENCES devises (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_movement_lines DROP FOREIGN KEY FK_1558E2C8229E70A7');
        $this->addSql('ALTER TABLE role_permissions DROP FOREIGN KEY FK_1FBA94E6FED90CCA');
        $this->addSql('ALTER TABLE utilisateur_permissions DROP FOREIGN KEY FK_D90BC1FCFED90CCA');
        $this->addSql('ALTER TABLE pays DROP FOREIGN KEY FK_349F3CAEF4445056');
        $this->addSql('ALTER TABLE pays_devise_liaisons DROP FOREIGN KEY FK_682FBD79A6E44244');
        $this->addSql('ALTER TABLE pays_devise_liaisons DROP FOREIGN KEY FK_682FBD79F4445056');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE utilisateurs');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE client_comments');
        $this->addSql('DROP TABLE sites');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE project_events');
        $this->addSql('DROP TABLE project_sites');
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE financial_transactions');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE equipment');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE stock_movement_lines');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE historique_actions');
        $this->addSql('DROP TABLE permissions');
        $this->addSql('DROP TABLE role_permissions');
        $this->addSql('DROP TABLE utilisateur_permissions');
        $this->addSql('DROP TABLE devises');
        $this->addSql('DROP TABLE historique_taux');
        $this->addSql('DROP TABLE pays');
        $this->addSql('DROP TABLE pays_devise_liaisons');
        $this->addSql('DROP TABLE reference_sequences');
        $this->addSql('DROP TABLE historique_settings');
        $this->addSql('DROP TABLE settings');
    }
}

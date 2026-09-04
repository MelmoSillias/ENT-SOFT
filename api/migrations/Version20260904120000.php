<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adresse client (service) + unité sur lignes de facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE clients ADD COLUMN address VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD COLUMN postal_box VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE clients ADD COLUMN city VARCHAR(100) DEFAULT NULL');
        $this->addSql("ALTER TABLE invoice_lines ADD COLUMN unit VARCHAR(50) DEFAULT 'Lot' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // SQLite: colonnes non retirables proprement sans reconstruction
    }
}

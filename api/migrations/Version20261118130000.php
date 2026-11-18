<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le choix persistant du modèle de facture classique ou moderne';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO settings (cle, valeur, type, description, created_at, updated_at) SELECT 'IMPRESSION_INVOICE_TEMPLATE', 'classic', 'STRING', 'Modèle de facture (classic|modern)', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP WHERE NOT EXISTS (SELECT 1 FROM settings WHERE cle = 'IMPRESSION_INVOICE_TEMPLATE')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM historique_settings WHERE setting_cle = 'IMPRESSION_INVOICE_TEMPLATE'");
        $this->addSql("DELETE FROM settings WHERE cle = 'IMPRESSION_INVOICE_TEMPLATE'");
    }
}

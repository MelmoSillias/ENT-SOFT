<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Libellé projet libre sur les factures (affichage impression)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices ADD COLUMN project_label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite: colonnes non retirables proprement
    }
}

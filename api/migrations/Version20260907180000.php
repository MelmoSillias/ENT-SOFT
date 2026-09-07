<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add business date column to prestations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestations ADD COLUMN date DATE DEFAULT NULL');
        $this->addSql("UPDATE prestations SET date = date(created_at) WHERE date IS NULL");
        // SQLite cannot easily enforce NOT NULL after backfill without rebuild; app always sets date.
    }

    public function down(Schema $schema): void
    {
        // SQLite: drop via rebuild if needed; leave column on down for safety.
        $this->addSql('SELECT 1');
    }
}

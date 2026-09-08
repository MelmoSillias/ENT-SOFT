<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908063000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add start_at / end_at datetime columns to tasks (resource timeline)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD COLUMN start_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD COLUMN end_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // SQLite: drop via rebuild if needed; leave columns on down for safety.
        $this->addSql('SELECT 1');
    }
}

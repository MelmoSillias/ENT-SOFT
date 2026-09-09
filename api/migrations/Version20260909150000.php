<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable latitude/longitude to sites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sites ADD latitude DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE sites ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sites DROP latitude');
        $this->addSql('ALTER TABLE sites DROP longitude');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add photo_url on employees, prestataires and utilisateurs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employees ADD photo_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE prestataires ADD photo_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateurs ADD photo_url VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employees DROP photo_url');
        $this->addSql('ALTER TABLE prestataires DROP photo_url');
        $this->addSql('ALTER TABLE utilisateurs DROP photo_url');
    }
}

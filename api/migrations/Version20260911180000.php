<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Élargit settings.valeur et historique pour listes paramétrables (émetteurs / destinataires)';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE settings MODIFY valeur TEXT NOT NULL');
            $this->addSql('ALTER TABLE historique_settings MODIFY ancienne_valeur TEXT NOT NULL');
            $this->addSql('ALTER TABLE historique_settings MODIFY nouvelle_valeur TEXT NOT NULL');
        }
        // SQLite : VARCHAR n'impose pas de longueur — le mapping Doctrine type text suffit.
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $this->addSql('ALTER TABLE settings MODIFY valeur VARCHAR(500) NOT NULL');
            $this->addSql('ALTER TABLE historique_settings MODIFY ancienne_valeur VARCHAR(500) NOT NULL');
            $this->addSql('ALTER TABLE historique_settings MODIFY nouvelle_valeur VARCHAR(500) NOT NULL');
        }
    }
}

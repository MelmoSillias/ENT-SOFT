<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du logo dédié des clients';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('clients')->addColumn('logo_url', 'string', ['length' => 255, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('clients')->dropColumn('logo_url');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajout du Social ID facultatif des employés'; }

    public function up(Schema $schema): void
    {
        $schema->getTable('employees')->addColumn('social_id', 'string', ['length' => 100, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('employees')->dropColumn('social_id');
    }
}

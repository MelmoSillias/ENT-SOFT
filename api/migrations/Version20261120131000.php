<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Objet facultatif des bons de livraison';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('project_documents')->addColumn('object', 'string', ['length' => 500, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('project_documents')->dropColumn('object');
    }
}

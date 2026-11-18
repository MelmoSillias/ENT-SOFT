<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des conditions de paiement des factures Finance en JSON nullable';
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('invoices')->addColumn('payment_terms', 'json', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('invoices')->dropColumn('payment_terms');
    }
}

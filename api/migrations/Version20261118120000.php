<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajout de la TVA optionnelle aux factures Finance, désactivée pour les factures existantes'; }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('invoices');
        $table->addColumn('vat_enabled', 'boolean', ['default' => false]);
        $table->addColumn('vat_rate', 'float', ['default' => 18]);
        // NULL conserve le montant historique comme base HT sans réécrire les factures.
        $table->addColumn('subtotal', 'float', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('invoices');
        $table->dropColumn('subtotal');
        $table->dropColumn('vat_rate');
        $table->dropColumn('vat_enabled');
    }
}

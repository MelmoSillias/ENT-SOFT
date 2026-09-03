<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lignes de facture, lien paiement, direction stock, statuts facture';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice_lines (description VARCHAR(255) NOT NULL, quantity DOUBLE PRECISION NOT NULL, unit_price DOUBLE PRECISION NOT NULL, amount DOUBLE PRECISION NOT NULL, id BLOB NOT NULL, invoice_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_INVOICE_LINES_INVOICE FOREIGN KEY (invoice_id) REFERENCES invoices (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_INVOICE_LINES_INVOICE ON invoice_lines (invoice_id)');
        $this->addSql('ALTER TABLE financial_transactions ADD COLUMN invoice_id BLOB DEFAULT NULL');
        $this->addSql("ALTER TABLE stock_movements ADD COLUMN direction VARCHAR(255) DEFAULT 'in' NOT NULL");
        $this->addSql("UPDATE invoices SET status = 'invoiced' WHERE status IN ('sent', 'paid')");
        $this->addSql("UPDATE invoices SET status = 'draft' WHERE status = 'cancelled'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoice_lines');
    }
}

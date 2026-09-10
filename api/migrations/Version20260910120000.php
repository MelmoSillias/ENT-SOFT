<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ordre des lignes de facture (position)';
    }

    public function up(Schema $schema): void
    {
        // executeStatement immédiat : addSql serait joué après le backfill PHP
        $this->connection->executeStatement(
            'ALTER TABLE invoice_lines ADD COLUMN position INTEGER DEFAULT 0 NOT NULL',
        );

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, invoice_id FROM invoice_lines ORDER BY invoice_id, rowid',
        );

        $positionByInvoice = [];
        foreach ($rows as $row) {
            $invoiceId = (string) $row['invoice_id'];
            $position = $positionByInvoice[$invoiceId] ?? 0;
            $positionByInvoice[$invoiceId] = $position + 1;
            $this->connection->executeStatement(
                'UPDATE invoice_lines SET position = ? WHERE id = ?',
                [$position, $row['id']],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // SQLite: colonnes non retirables proprement sans reconstruction
    }
}

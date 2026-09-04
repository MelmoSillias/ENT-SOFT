<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Double numérotation facture (séquentiel + mensuel) et séquences mensuelles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice_monthly_sequences (year_month VARCHAR(7) NOT NULL, derniere_valeur INTEGER NOT NULL, PRIMARY KEY (year_month))');
        $this->addSql("ALTER TABLE invoices ADD COLUMN number_monthly VARCHAR(50) DEFAULT '' NOT NULL");
    }

    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;

        $rows = $conn->fetchAllAssociative(
            'SELECT id, number, date, created_at FROM invoices ORDER BY date ASC, created_at ASC, id ASC'
        );

        $ranksByMonth = [];
        $maxByMonth = [];

        foreach ($rows as $row) {
            $date = new \DateTimeImmutable((string) $row['date']);
            $yearMonth = $date->format('Y-m');
            $month = (int) $date->format('n');
            $year = (int) $date->format('Y');

            $ranksByMonth[$yearMonth] = ($ranksByMonth[$yearMonth] ?? 0) + 1;
            $rank = $ranksByMonth[$yearMonth];
            $maxByMonth[$yearMonth] = $rank;

            $monthly = sprintf('ENT%d/%d-%d', $rank, $month, $year);
            $sequential = ltrim((string) $row['number'], '0');
            if ($sequential === '') {
                $sequential = (string) $row['number'];
            }

            $conn->executeStatement(
                'UPDATE invoices SET number_monthly = ?, number = ? WHERE id = ?',
                [$monthly, $sequential, $row['id']],
            );
        }

        foreach ($maxByMonth as $yearMonth => $max) {
            $conn->executeStatement(
                'INSERT INTO invoice_monthly_sequences (year_month, derniere_valeur) VALUES (?, ?)',
                [$yearMonth, $max],
            );
        }

        $conn->executeStatement('CREATE UNIQUE INDEX uniq_invoice_number_monthly ON invoices (number_monthly)');
    }

    public function down(Schema $schema): void
    {
        // SQLite: rollback partiel non supporté proprement
    }
}

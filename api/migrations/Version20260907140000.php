<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260907140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prestation payment allocations for multi-prestation payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE prestation_payment_allocations (
            transaction_id BLOB NOT NULL,
            prestation_id BLOB NOT NULL,
            amount DOUBLE PRECISION NOT NULL,
            id BLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_enabled BOOLEAN DEFAULT 1 NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE INDEX idx_ppa_prestation ON prestation_payment_allocations (prestation_id)');
        $this->addSql('CREATE INDEX idx_ppa_transaction ON prestation_payment_allocations (transaction_id)');
    }

    public function postUp(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, prestation_id, amount, created_at, updated_at
             FROM financial_transactions
             WHERE category = 'PrestationPayment'
               AND prestation_id IS NOT NULL",
        );

        foreach ($rows as $row) {
            $this->connection->insert('prestation_payment_allocations', [
                'id' => Uuid::v7()->toBinary(),
                'transaction_id' => $row['id'],
                'prestation_id' => $row['prestation_id'],
                'amount' => $row['amount'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'is_enabled' => 1,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE prestation_payment_allocations');
    }
}

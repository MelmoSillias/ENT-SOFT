<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create upload_sessions table (chunked upload module)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE upload_sessions (public_id VARCHAR(32) NOT NULL, owner_id BLOB NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(128) DEFAULT NULL, byte_size INTEGER NOT NULL, checksum VARCHAR(64) DEFAULT NULL, chunk_size INTEGER NOT NULL, chunk_count INTEGER NOT NULL, received_chunks CLOB NOT NULL, status VARCHAR(24) NOT NULL, assembled_path VARCHAR(512) DEFAULT NULL, attached_path VARCHAR(512) DEFAULT NULL, expires_at DATETIME NOT NULL, id BLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_upload_session_public_id ON upload_sessions (public_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE upload_sessions');
    }
}

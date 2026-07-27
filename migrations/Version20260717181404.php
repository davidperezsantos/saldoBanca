<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717181404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add balance_operation_event table for per-record status timelines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE balance_operation_event (entity_type VARCHAR(30) NOT NULL, entity_id VARCHAR(36) NOT NULL, status VARCHAR(30) NOT NULL, performedBy VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, id UUID NOT NULL, createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_operation_event_entity ON balance_operation_event (entity_type, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE balance_operation_event');
    }
}

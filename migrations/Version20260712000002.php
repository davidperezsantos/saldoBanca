<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create balance_exchange_rate_snapshot table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE balance_exchange_rate_snapshot (
            id UUID NOT NULL,
            base VARCHAR(10) NOT NULL,
            rates JSON NOT NULL,
            timestamp_api BIGINT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE balance_exchange_rate_snapshot');
    }
}

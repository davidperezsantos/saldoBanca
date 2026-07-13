<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712192654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop isdefault from provider, align snapshot column names with convention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP isdefault');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN created_at TO createdat');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN updated_at TO updateat');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN timestamp_api TO timestampapi');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD isdefault BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN createdat TO created_at');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN updateat TO updated_at');
        $this->addSql('ALTER TABLE balance_exchange_rate_snapshot RENAME COLUMN timestampapi TO timestamp_api');
    }
}

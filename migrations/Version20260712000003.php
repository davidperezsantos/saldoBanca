<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add apiKey, username, password, token, authType, isActive to exchange_rate_provider';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD api_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD username VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD password VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD token VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD auth_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ADD is_active BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP api_key');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP username');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP password');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP token');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP auth_type');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider DROP is_active');
    }
}

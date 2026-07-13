<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add originalAmount, originalCurrency, exchangeRate to balance_recharge';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_recharge ADD original_amount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_recharge ADD original_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_recharge ADD exchange_rate NUMERIC(18, 8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_recharge DROP original_amount');
        $this->addSql('ALTER TABLE balance_recharge DROP original_currency');
        $this->addSql('ALTER TABLE balance_recharge DROP exchange_rate');
    }
}

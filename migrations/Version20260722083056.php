<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722083056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el split de pago en dos monedas (payout percents en balance_account, columnas de liquidación en balance_business_reconciliation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account ADD payoutCurrencyPercent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_account ADD payoutSecondaryCurrencyPercent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementBaseCurrency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementBaseAmount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementSecondaryCurrency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementSecondaryAmount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementExchangeRate NUMERIC(18, 4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account DROP payoutCurrencyPercent');
        $this->addSql('ALTER TABLE balance_account DROP payoutSecondaryCurrencyPercent');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementBaseCurrency');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementBaseAmount');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementSecondaryCurrency');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementSecondaryAmount');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementExchangeRate');
    }
}

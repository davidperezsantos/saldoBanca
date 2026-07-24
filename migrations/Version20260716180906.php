<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facturas en monedas distintas a la base del sistema: balance_invoice_payment gana
 * original_amount/original_currency/exchange_rate, igual que balance_recharge/balance_transfer.
 */
final class Version20260716180906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversión de moneda en facturas: original_amount/original_currency/exchange_rate en balance_invoice_payment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment ADD original_amount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_invoice_payment ADD original_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_invoice_payment ADD exchange_rate NUMERIC(18, 8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment DROP original_amount');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP original_currency');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP exchange_rate');
    }
}

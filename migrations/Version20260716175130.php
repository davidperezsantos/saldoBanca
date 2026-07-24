<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Transferencias entre cuentas en monedas distintas a la base del sistema: balance_transfer gana
 * original_amount/original_currency/exchange_rate, igual que balance_recharge, para registrar el
 * monto/moneda tal como los pidió el usuario antes de convertirlos.
 */
final class Version20260716175130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversión de moneda en transferencias: original_amount/original_currency/exchange_rate en balance_transfer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_transfer ADD original_amount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_transfer ADD original_currency VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_transfer ADD exchange_rate NUMERIC(18, 8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_transfer DROP original_amount');
        $this->addSql('ALTER TABLE balance_transfer DROP original_currency');
        $this->addSql('ALTER TABLE balance_transfer DROP exchange_rate');
    }
}

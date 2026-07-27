<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715135001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Defensa en profundidad: evita saldos negativos en balance_account_balance a nivel de BD, además de la validación de aplicación en BalanceService.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account_balance ADD CONSTRAINT chk_available_balance_non_negative CHECK (availablebalance >= 0)');
        $this->addSql('ALTER TABLE balance_account_balance ADD CONSTRAINT chk_reserved_balance_non_negative CHECK (reservedbalance >= 0)');
        $this->addSql('ALTER TABLE balance_account_balance ADD CONSTRAINT chk_pending_balance_non_negative CHECK (pendingbalance >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account_balance DROP CONSTRAINT chk_available_balance_non_negative');
        $this->addSql('ALTER TABLE balance_account_balance DROP CONSTRAINT chk_reserved_balance_non_negative');
        $this->addSql('ALTER TABLE balance_account_balance DROP CONSTRAINT chk_pending_balance_non_negative');
    }
}

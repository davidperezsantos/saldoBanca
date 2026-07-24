<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 5: maxPerTransfer/maxDaily/maxMonthly en balance_account — antes solo existían como
 * columnas de solo-informe en la respuesta de TransferService::getTransferLimits() (nunca se
 * validaban); ahora TransferService::createTransfer/processTransfer los hace cumplir de verdad.
 * NULL = sin límite (comportamiento igual al actual para cuentas existentes).
 */
final class Version20260716115749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 5: agrega maxPerTransfer/maxDaily/maxMonthly a balance_account, con enforcement real en TransferService.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account ADD maxPerTransfer NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_account ADD maxDaily NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_account ADD maxMonthly NUMERIC(18, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account DROP maxPerTransfer');
        $this->addSql('ALTER TABLE balance_account DROP maxDaily');
        $this->addSql('ALTER TABLE balance_account DROP maxMonthly');
    }
}

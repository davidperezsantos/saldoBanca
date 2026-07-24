<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Facturas: se separan las dos cuentas involucradas. `account` (ya existía, obligatoria) es el
 * cliente al que se le descuenta el saldo. `business_account_id` (nueva, opcional) es el negocio
 * que realizó la operación — solo informativo, no se le mueve saldo; si se manda debe ser una
 * cuenta accountType=business (validado en InvoiceService::resolveBusinessAccount()).
 */
final class Version20260716190505 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facturas: agrega business_account_id (opcional) en balance_invoice_payment para el negocio que realizó la operación, separado del cliente.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment ADD business_account_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_invoice_payment ADD CONSTRAINT FK_A8C7A5035BC85711 FOREIGN KEY (business_account_id) REFERENCES balance_account (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A8C7A5035BC85711 ON balance_invoice_payment (business_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment DROP CONSTRAINT FK_A8C7A5035BC85711');
        $this->addSql('DROP INDEX IDX_A8C7A5035BC85711');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP business_account_id');
    }
}

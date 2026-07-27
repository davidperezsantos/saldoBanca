<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Cuentas reales de pago del negocio (balance_business_payout_account) + código único
 * (reconciliationNumber/receiptNumber) para conciliaciones y facturas, mismo patrón de
 * DocumentNumberService que recharge/transfer (ver Version20260716162259). Se agregan también las
 * FK de conciliación -> cuenta de pago elegida por el negocio al aprobar.
 */
final class Version20260723160150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cuentas reales de pago del negocio + código único para conciliaciones y facturas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_business_payout_account (
              alias VARCHAR(100) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              bankName VARCHAR(100) DEFAULT NULL,
              accountNumber VARCHAR(50) NOT NULL,
              swift VARCHAR(20) DEFAULT NULL,
              accountHolder VARCHAR(150) DEFAULT NULL,
              isActive BOOLEAN NOT NULL,
              id UUID NOT NULL,
              createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              account_id UUID NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_377907FA9B6B5FBA ON balance_business_payout_account (account_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_business_payout_account
            ADD
              CONSTRAINT FK_377907FA9B6B5FBA FOREIGN KEY (account_id) REFERENCES balance_account (id) NOT DEFERRABLE
        SQL);

        $this->addSql('ALTER TABLE balance_business_reconciliation ADD reconciliationNumber VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD payout_account_base_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD payout_account_secondary_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_business_reconciliation
            ADD
              CONSTRAINT FK_681DF076AB93608F FOREIGN KEY (payout_account_base_id) REFERENCES balance_business_payout_account (id) NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_business_reconciliation
            ADD
              CONSTRAINT FK_681DF0769DA87EBA FOREIGN KEY (payout_account_secondary_id) REFERENCES balance_business_payout_account (id) NOT DEFERRABLE
        SQL);
        $this->addSql('CREATE INDEX IDX_681DF076AB93608F ON balance_business_reconciliation (payout_account_base_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_681DF0769DA87EBA ON balance_business_reconciliation (payout_account_secondary_id)
        SQL);

        $this->addSql('ALTER TABLE balance_invoice_payment ADD receiptNumber VARCHAR(20) DEFAULT NULL');

        // Sembrar los dos tipos de secuencia nuevos, arrancando en 0 igual que Version20260716162259.
        $this->addSql(<<<'SQL'
            INSERT INTO balance_document_sequence (documentType, lastValue)
            VALUES ('reconciliation', 0), ('invoice_receipt', 0)
        SQL);

        // Backfill de conciliaciones/facturas ya existentes, numeradas en orden de creación.
        $this->addSql(<<<'SQL'
            WITH numbered AS (
              SELECT id, ROW_NUMBER() OVER (ORDER BY createdat) AS rn FROM balance_business_reconciliation
            )
            UPDATE balance_business_reconciliation r
            SET reconciliationNumber = 'CON-' || LPAD(numbered.rn::text, 8, '0')
            FROM numbered
            WHERE numbered.id = r.id
        SQL);
        $this->addSql(<<<'SQL'
            WITH numbered AS (
              SELECT id, ROW_NUMBER() OVER (ORDER BY createdat) AS rn FROM balance_invoice_payment
            )
            UPDATE balance_invoice_payment i
            SET receiptNumber = 'FAC-' || LPAD(numbered.rn::text, 8, '0')
            FROM numbered
            WHERE numbered.id = i.id
        SQL);

        // El contador continúa después del máximo ya asignado en el backfill.
        $this->addSql(<<<'SQL'
            UPDATE balance_document_sequence
            SET lastValue = (SELECT COUNT(*) FROM balance_business_reconciliation)
            WHERE documentType = 'reconciliation'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE balance_document_sequence
            SET lastValue = (SELECT COUNT(*) FROM balance_invoice_payment)
            WHERE documentType = 'invoice_receipt'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_681DF076C2C8A43E ON balance_business_reconciliation (reconciliationNumber)
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A8C7A503FF9C42CE ON balance_invoice_payment (receiptNumber)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_681DF076C2C8A43E');
        $this->addSql('DROP INDEX UNIQ_A8C7A503FF9C42CE');
        $this->addSql("DELETE FROM balance_document_sequence WHERE documentType IN ('reconciliation', 'invoice_receipt')");
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP CONSTRAINT FK_681DF076AB93608F');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP CONSTRAINT FK_681DF0769DA87EBA');
        $this->addSql('DROP INDEX IDX_681DF076AB93608F');
        $this->addSql('DROP INDEX IDX_681DF0769DA87EBA');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP reconciliationNumber');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP payout_account_base_id');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP payout_account_secondary_id');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP receiptNumber');
        $this->addSql('ALTER TABLE balance_business_payout_account DROP CONSTRAINT FK_377907FA9B6B5FBA');
        $this->addSql('DROP TABLE balance_business_payout_account');
    }
}

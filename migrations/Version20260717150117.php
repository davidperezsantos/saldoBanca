<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717150117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add business reconciliation tables and reconciliation_id on invoice payments';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE balance_business_reconciliation (periodStart DATE NOT NULL, periodEnd DATE NOT NULL, invoiceCount INT NOT NULL, totalAmount NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(20) NOT NULL, approvalToken VARCHAR(64) NOT NULL, tokenExpiresAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, createdBy VARCHAR(255) DEFAULT NULL, sentAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, businessApprovedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, businessApprovedBy VARCHAR(255) DEFAULT NULL, adminApprovedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, adminApprovedBy VARCHAR(255) DEFAULT NULL, rejectedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, rejectedBy VARCHAR(255) DEFAULT NULL, rejectionReason TEXT DEFAULT NULL, settledAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, settledBy VARCHAR(255) DEFAULT NULL, settlementMethod VARCHAR(20) DEFAULT NULL, settlementReference VARCHAR(100) DEFAULT NULL, settlementNotes TEXT DEFAULT NULL, id UUID NOT NULL, createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, business_account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_681DF0761703E4CB ON balance_business_reconciliation (approvalToken)');
        $this->addSql('CREATE INDEX IDX_681DF0765BC85711 ON balance_business_reconciliation (business_account_id)');
        $this->addSql('CREATE TABLE balance_business_reconciliation_event (eventType VARCHAR(30) NOT NULL, performedBy VARCHAR(255) DEFAULT NULL, notes TEXT DEFAULT NULL, metadata JSON DEFAULT NULL, id UUID NOT NULL, createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reconciliation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C06E4D607A288E76 ON balance_business_reconciliation_event (reconciliation_id)');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD CONSTRAINT FK_681DF0765BC85711 FOREIGN KEY (business_account_id) REFERENCES balance_account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE balance_business_reconciliation_event ADD CONSTRAINT FK_C06E4D607A288E76 FOREIGN KEY (reconciliation_id) REFERENCES balance_business_reconciliation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE balance_invoice_payment ADD reconciliation_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_invoice_payment ADD CONSTRAINT FK_A8C7A5037A288E76 FOREIGN KEY (reconciliation_id) REFERENCES balance_business_reconciliation (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A8C7A5037A288E76 ON balance_invoice_payment (reconciliation_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP CONSTRAINT FK_681DF0765BC85711');
        $this->addSql('ALTER TABLE balance_business_reconciliation_event DROP CONSTRAINT FK_C06E4D607A288E76');
        $this->addSql('DROP TABLE balance_business_reconciliation');
        $this->addSql('DROP TABLE balance_business_reconciliation_event');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP CONSTRAINT FK_A8C7A5037A288E76');
        $this->addSql('DROP INDEX IDX_A8C7A5037A288E76');
        $this->addSql('ALTER TABLE balance_invoice_payment DROP reconciliation_id');
    }
}

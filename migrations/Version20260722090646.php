<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722090646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el PIN de verificación por WhatsApp a la aprobación pública de conciliaciones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD approvalPinHash VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD approvalPinExpiresAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD approvalPinAttempts INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD approvalPinRequestedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD approvalPinVerifiedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP approvalPinHash');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP approvalPinExpiresAt');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP approvalPinAttempts');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP approvalPinRequestedAt');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP approvalPinVerifiedAt');
    }
}

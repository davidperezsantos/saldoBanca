<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * PIN de verificación por WhatsApp para las acciones sensibles de CommissionSettlement realizadas
 * vía /api/v1 (sin sesión de panel) — mismo patrón que BusinessReconciliation::approvalPin*.
 */
final class Version20260723182512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PIN de verificación para acciones de CommissionSettlement vía API.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_commission_settlement ADD approvalPinHash VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_commission_settlement
            ADD
              approvalPinExpiresAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE balance_commission_settlement ADD approvalPinAttempts INT NOT NULL DEFAULT 0');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_commission_settlement
            ADD
              approvalPinRequestedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_commission_settlement
            ADD
              approvalPinVerifiedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        $this->addSql('ALTER TABLE balance_commission_settlement ADD pinVerifiedFor VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_commission_settlement DROP approvalPinHash');
        $this->addSql('ALTER TABLE balance_commission_settlement DROP approvalPinExpiresAt');
        $this->addSql('ALTER TABLE balance_commission_settlement DROP approvalPinAttempts');
        $this->addSql('ALTER TABLE balance_commission_settlement DROP approvalPinRequestedAt');
        $this->addSql('ALTER TABLE balance_commission_settlement DROP approvalPinVerifiedAt');
        $this->addSql('ALTER TABLE balance_commission_settlement DROP pinVerifiedFor');
    }
}

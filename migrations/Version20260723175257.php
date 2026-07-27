<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Liquidación de la comisión del sistema entre el Administrador y el Super Administrador
 * (balance_commission_settlement) + código único (settlementNumber) vía DocumentNumberService,
 * mismo patrón que las conciliaciones con negocios (ver Version20260716162259).
 */
final class Version20260723175257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liquidación de comisión del sistema entre Administrador y Super Administrador.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_commission_settlement (
              settlementNumber VARCHAR(20) DEFAULT NULL,
              amount NUMERIC(18, 2) NOT NULL,
              currency VARCHAR(3) NOT NULL,
              status VARCHAR(25) NOT NULL,
              createdBy VARCHAR(255) DEFAULT NULL,
              notes TEXT DEFAULT NULL,
              adminApprovedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              adminApprovedBy VARCHAR(255) DEFAULT NULL,
              payoutAccountNumber VARCHAR(100) DEFAULT NULL,
              payoutBankName VARCHAR(100) DEFAULT NULL,
              payoutAccountHolder VARCHAR(150) DEFAULT NULL,
              accountAssignedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              accountAssignedBy VARCHAR(255) DEFAULT NULL,
              settlementMethod VARCHAR(20) DEFAULT NULL,
              settlementReference VARCHAR(100) DEFAULT NULL,
              settledAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              settledBy VARCHAR(255) DEFAULT NULL,
              closedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              closedBy VARCHAR(255) DEFAULT NULL,
              id UUID NOT NULL,
              createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_268F4CF221AC3032 ON balance_commission_settlement (settlementNumber)');

        $this->addSql(<<<'SQL'
            INSERT INTO balance_document_sequence (documentType, lastValue)
            VALUES ('commission_settlement', 0)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM balance_document_sequence WHERE documentType = 'commission_settlement'");
        $this->addSql('DROP TABLE balance_commission_settlement');
    }
}

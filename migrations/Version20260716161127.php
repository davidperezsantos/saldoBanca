<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 5: tabla balance_bank_reconciliation — un registro por corrida de conciliación bancaria
 * (ReconciliationService::reconcile), con los contadores de descuadre y el detalle en JSON.
 */
final class Version20260716161127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 5: tabla balance_bank_reconciliation para el proceso de conciliación bancaria.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_bank_reconciliation (
              gatewayCode VARCHAR(100) NOT NULL,
              periodStart TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              periodEnd TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              totalExternal INT NOT NULL,
              totalMatched INT NOT NULL,
              totalMismatched INT NOT NULL,
              totalMissingInternal INT NOT NULL,
              totalMissingExternal INT NOT NULL,
              discrepancies JSON DEFAULT NULL,
              performedBy VARCHAR(255) DEFAULT NULL,
              id UUID NOT NULL,
              createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE balance_bank_reconciliation');
    }
}

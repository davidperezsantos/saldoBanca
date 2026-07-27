<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715142504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 3: tabla de eventos de webhook (balance_webhook_event) e índice único parcial de idempotencia en balance_recharge (externalsystem, referencenumber).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_webhook_event (
              gatewayCode VARCHAR(100) NOT NULL,
              payload JSON DEFAULT NULL,
              signatureValid BOOLEAN NOT NULL,
              status VARCHAR(30) NOT NULL,
              errorMessage TEXT DEFAULT NULL,
              id UUID NOT NULL,
              createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              gateway_id UUID DEFAULT NULL,
              recharge_id UUID DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_933228DC577F8E00 ON balance_webhook_event (gateway_id)');
        $this->addSql('CREATE INDEX IDX_933228DC4926C850 ON balance_webhook_event (recharge_id)');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_webhook_event
            ADD
              CONSTRAINT FK_933228DC577F8E00 FOREIGN KEY (gateway_id) REFERENCES balance_payment_gateway (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_webhook_event
            ADD
              CONSTRAINT FK_933228DC4926C850 FOREIGN KEY (recharge_id) REFERENCES balance_recharge (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);

        // Idempotencia: dos recargas del mismo par (pasarela, referencia) no pueden coexistir.
        // Índice parcial: las recargas manuales (sin externalsystem/referencenumber) no se restringen.
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_recharge_external_reference
            ON balance_recharge (externalsystem, referencenumber)
            WHERE externalsystem IS NOT NULL AND referencenumber IS NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_recharge_external_reference');
        $this->addSql('ALTER TABLE balance_webhook_event DROP CONSTRAINT FK_933228DC577F8E00');
        $this->addSql('ALTER TABLE balance_webhook_event DROP CONSTRAINT FK_933228DC4926C850');
        $this->addSql('DROP TABLE balance_webhook_event');
    }
}

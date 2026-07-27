<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 5: numeración correlativa propia para comprobantes generados por el sistema. Tabla
 * balance_document_sequence (un contador por tipo, pre-sembrado para que el primer
 * DocumentSequenceRepository::findForUpdate() siempre bloquee una fila existente en vez de competir
 * por insertarla) + columna receiptNumber en balance_recharge/balance_transfer, con backfill de las
 * filas existentes en orden de creación para que el contador arranque después del máximo ya usado.
 */
final class Version20260716162259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 5: numeración correlativa (receiptNumber) para recargas y transferencias.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_document_sequence (
              documentType VARCHAR(50) NOT NULL,
              lastValue BIGINT NOT NULL,
              PRIMARY KEY (documentType)
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO balance_document_sequence (documentType, lastValue)
            VALUES ('recharge_receipt', 0), ('transfer_receipt', 0)
        SQL);

        $this->addSql('ALTER TABLE balance_recharge ADD receiptNumber VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_transfer ADD receiptNumber VARCHAR(30) DEFAULT NULL');

        // Backfill de recargas/transferencias ya existentes, numeradas en orden de creación.
        $this->addSql(<<<'SQL'
            WITH numbered AS (
              SELECT id, ROW_NUMBER() OVER (ORDER BY createdat) AS rn FROM balance_recharge
            )
            UPDATE balance_recharge r
            SET receiptNumber = 'REC-' || LPAD(numbered.rn::text, 8, '0')
            FROM numbered
            WHERE numbered.id = r.id
        SQL);
        $this->addSql(<<<'SQL'
            WITH numbered AS (
              SELECT id, ROW_NUMBER() OVER (ORDER BY createdat) AS rn FROM balance_transfer
            )
            UPDATE balance_transfer t
            SET receiptNumber = 'TRA-' || LPAD(numbered.rn::text, 8, '0')
            FROM numbered
            WHERE numbered.id = t.id
        SQL);

        // El contador continúa después del máximo ya asignado en el backfill.
        $this->addSql(<<<'SQL'
            UPDATE balance_document_sequence
            SET lastValue = (SELECT COUNT(*) FROM balance_recharge)
            WHERE documentType = 'recharge_receipt'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE balance_document_sequence
            SET lastValue = (SELECT COUNT(*) FROM balance_transfer)
            WHERE documentType = 'transfer_receipt'
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_9DF09EE1FF9C42CE ON balance_recharge (receiptNumber)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6BB41270FF9C42CE ON balance_transfer (receiptNumber)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_9DF09EE1FF9C42CE');
        $this->addSql('DROP INDEX UNIQ_6BB41270FF9C42CE');
        $this->addSql('ALTER TABLE balance_recharge DROP receiptNumber');
        $this->addSql('ALTER TABLE balance_transfer DROP receiptNumber');
        $this->addSql('DROP TABLE balance_document_sequence');
    }
}

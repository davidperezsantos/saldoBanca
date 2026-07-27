<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715154820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 4: TransactionLog.account pasa a nullable (no toda mutación de /api resuelve a una sola cuenta) y onDelete SET NULL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_transaction_log DROP CONSTRAINT fk_434dcad19b6b5fba');
        $this->addSql('ALTER TABLE balance_transaction_log ALTER account_id DROP NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_transaction_log
            ADD
              CONSTRAINT FK_434DCAD19B6B5FBA FOREIGN KEY (account_id) REFERENCES balance_account (id) ON DELETE
            SET
              NULL NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_transaction_log DROP CONSTRAINT FK_434DCAD19B6B5FBA');
        $this->addSql('ALTER TABLE balance_transaction_log ALTER account_id SET NOT NULL');
        $this->addSql(<<<'SQL'
            ALTER TABLE
              balance_transaction_log
            ADD
              CONSTRAINT fk_434dcad19b6b5fba FOREIGN KEY (account_id) REFERENCES balance_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}

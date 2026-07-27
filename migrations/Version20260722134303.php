<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722134303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega la comisión del sistema (taxPercent/taxAmount/netAmount) a la conciliación';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD taxPercent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD taxAmount NUMERIC(18, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD netAmount NUMERIC(18, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP taxPercent');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP taxAmount');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP netAmount');
    }
}

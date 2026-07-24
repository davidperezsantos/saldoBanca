<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722121013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega el porcentaje fijado al crear la conciliación (settlementBasePercent/settlementSecondaryPercent)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementBasePercent NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementSecondaryPercent NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementBasePercent');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementSecondaryPercent');
    }
}

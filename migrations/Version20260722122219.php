<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722122219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega método/referencia de liquidación separados para la moneda secundaria del split';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementSecondaryMethod VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_business_reconciliation ADD settlementSecondaryReference VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementSecondaryMethod');
        $this->addSql('ALTER TABLE balance_business_reconciliation DROP settlementSecondaryReference');
    }
}

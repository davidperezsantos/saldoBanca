<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721190429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reduce balance_exchange_rate rate/inverseRate precision from 8 to 4 decimals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER rate TYPE NUMERIC(18, 4)');
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER inverseRate TYPE NUMERIC(18, 4)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER rate TYPE NUMERIC(18, 8)');
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER inverserate TYPE NUMERIC(18, 8)');
    }
}

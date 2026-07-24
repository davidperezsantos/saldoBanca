<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722160539 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega pinCode a balance_account (PIN para que el titular pague sus propias facturas)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account ADD pinCode VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account DROP pinCode');
    }
}

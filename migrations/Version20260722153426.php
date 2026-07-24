<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722153426 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega paymentMethod a balance_invoice_payment (pago vía autorizado: saldo o efectivo)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment ADD paymentMethod VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_invoice_payment DROP paymentMethod');
    }
}

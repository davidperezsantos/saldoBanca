<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715161432 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 4: reservedAmount en balance_authorized_user — cuánto queda del cupo reservado ' .
            'por autorizado (distinto de maxAmount, el tope fijo), para liberar/reactivar el monto ' .
            'real en vez del original completo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_authorized_user ADD reservedAmount NUMERIC(18, 2) DEFAULT NULL');
        // Backfill: los autorizados ya existentes con maxAmount asumen que no han gastado nada
        // todavía (reservedAmount = maxAmount completo).
        $this->addSql('UPDATE balance_authorized_user SET reservedAmount = maxamount WHERE maxamount IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_authorized_user DROP reservedAmount');
    }
}

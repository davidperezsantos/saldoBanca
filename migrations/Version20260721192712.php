<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260721192712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isLocked to balance_exchange_rate and make provider_id nullable (manual rates)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate ADD isLocked BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER provider_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_exchange_rate DROP isLocked');
        $this->addSql('ALTER TABLE balance_exchange_rate ALTER provider_id SET NOT NULL');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723090444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega pinRequestedAt (cooldown de reenvío) a balance_account y balance_authorized_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account ADD pinRequestedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_authorized_user ADD pinRequestedAt TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_account DROP pinRequestedAt');
        $this->addSql('ALTER TABLE balance_authorized_user DROP pinRequestedAt');
    }
}

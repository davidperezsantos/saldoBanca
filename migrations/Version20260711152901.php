<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260711152901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_account ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE balance_account ADD CONSTRAINT FK_49DD741EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_49DD741EA76ED395 ON balance_account (user_id)');
        $this->addSql('ALTER INDEX users_username_key RENAME TO UNIQ_1483A5E9F85E0677');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_account DROP CONSTRAINT FK_49DD741EA76ED395');
        $this->addSql('DROP INDEX UNIQ_49DD741EA76ED395');
        $this->addSql('ALTER TABLE balance_account DROP user_id');
        $this->addSql('ALTER INDEX uniq_1483a5e9f85e0677 RENAME TO users_username_key');
    }
}

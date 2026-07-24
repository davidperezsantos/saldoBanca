<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260715091520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_authorized_user DROP CONSTRAINT fk_5b5f5f7ba76ed395');
        $this->addSql('ALTER TABLE balance_authorized_user ADD CONSTRAINT FK_8140A940A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER INDEX uniq_5b5f5f7ba76ed395 RENAME TO UNIQ_8140A940A76ED395');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ALTER is_active DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.id IS \'\'');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.createdAt IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_authorized_user DROP CONSTRAINT FK_8140A940A76ED395');
        $this->addSql('ALTER TABLE balance_authorized_user ADD CONSTRAINT fk_5b5f5f7ba76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER INDEX uniq_8140a940a76ed395 RENAME TO uniq_5b5f5f7ba76ed395');
        $this->addSql('ALTER TABLE balance_exchange_rate_provider ALTER is_active SET DEFAULT false');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN balance_exchange_rate_snapshot.createdat IS \'(DC2Type:datetime_immutable)\'');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723145929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega roles.allowPanelLogin (default true) y lo pone en false para los roles ' .
            '"cliente"/"emprendedor" que ya existan — esos solo se asignan vía autorregistro ' .
            'público y no deben poder iniciar sesión en el panel admin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roles ADD allowPanelLogin BOOLEAN DEFAULT true NOT NULL');
        $this->addSql("UPDATE roles SET allowPanelLogin = false WHERE name IN ('cliente', 'emprendedor')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roles DROP allowPanelLogin');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Users.role_id (ManyToOne único) -> user_roles (ManyToMany) — un User puede necesitar más de un
 * Role a la vez (ej. admin + cliente). Migra los datos existentes antes de dropear la columna
 * vieja, no solo el esquema.
 */
final class Version20260728092326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'users.role_id (ManyToOne) -> tabla user_roles (ManyToMany), preservando los datos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_roles (user_id UUID NOT NULL, role_id UUID NOT NULL, PRIMARY KEY (user_id, role_id))');
        $this->addSql('CREATE INDEX IDX_54FCD59FA76ED395 ON user_roles (user_id)');
        $this->addSql('CREATE INDEX IDX_54FCD59FD60322AC ON user_roles (role_id)');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT FK_54FCD59FD60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');

        $this->addSql('INSERT INTO user_roles (user_id, role_id) SELECT id, role_id FROM users WHERE role_id IS NOT NULL');

        $this->addSql('ALTER TABLE users DROP CONSTRAINT fk_1483a5e9d60322ac');
        $this->addSql('DROP INDEX idx_1483a5e9d60322ac');
        $this->addSql('ALTER TABLE users DROP role_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD role_id UUID DEFAULT NULL');

        // Un usuario podía tener quedar con más de un rol asignado desde que corrió el up() — al
        // volver a la columna única se queda con uno cualquiera de ellos, no hay forma sin pérdida
        // de revertir "varios roles" a "uno solo".
        $this->addSql('UPDATE users SET role_id = (SELECT role_id FROM user_roles WHERE user_roles.user_id = users.id LIMIT 1)');

        $this->addSql('ALTER TABLE users ADD CONSTRAINT fk_1483a5e9d60322ac FOREIGN KEY (role_id) REFERENCES roles (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_1483a5e9d60322ac ON users (role_id)');

        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FA76ED395');
        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT FK_54FCD59FD60322AC');
        $this->addSql('DROP TABLE user_roles');
    }
}

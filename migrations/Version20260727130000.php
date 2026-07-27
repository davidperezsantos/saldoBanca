<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tabla "sessions" para PdoSessionHandler (ver framework.yaml/services.yaml). El deploy corre
 * (o puede correr) con más de una réplica sin filesystem compartido — con sesiones en archivo,
 * cada request podía caer en un contenedor distinto sin la sesión de otro, deslogueando al
 * usuario de forma intermitente. Guardarlas en Postgres las hace visibles a cualquier réplica.
 *
 * Esquema exacto que exige PdoSessionHandler::createTable() para "pgsql" — no es una entidad de
 * Doctrine, Symfony administra esta tabla directo por PDO.
 */
final class Version20260727130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla sessions para PdoSessionHandler (sesiones compartidas entre réplicas).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE sessions (
                sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
                sess_data BYTEA NOT NULL,
                sess_lifetime INTEGER NOT NULL,
                sess_time INTEGER NOT NULL
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
    }
}

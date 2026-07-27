<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fase 5: pincode de balance_authorized_user pasa de texto plano a hash (mismo mecanismo que
 * User::password vía Symfony PasswordHasher) — autoriza montos de dinero, debería tratarse como
 * credencial. Se ensancha la columna para que quepa el hash y se rehashean en el propio up() los
 * PIN existentes (aún en texto plano de 4 dígitos); no se puede hacer con SQL puro porque el hash
 * se calcula en PHP.
 */
final class Version20260716111332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fase 5: hashea pincode en balance_authorized_user (antes texto plano) y ensancha la columna.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE balance_authorized_user ALTER pincode TYPE VARCHAR(255)');

        // Rehash de los PIN existentes (todavía en texto plano, 4 dígitos) — no se puede hacer con
        // addSql() porque el hash se calcula en PHP, no en SQL.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, pincode FROM balance_authorized_user WHERE pincode IS NOT NULL AND length(pincode) <= 4'
        );

        foreach ($rows as $row) {
            $hash = password_hash($row['pincode'], PASSWORD_DEFAULT);
            $this->connection->executeStatement(
                'UPDATE balance_authorized_user SET pincode = :hash WHERE id = :id',
                ['hash' => $hash, 'id' => $row['id']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible: no se puede recuperar el PIN en texto plano a partir del hash. Se limpia el
        // campo para no dejar hashes en una columna que vuelve a ser VARCHAR(4).
        $this->addSql('UPDATE balance_authorized_user SET pincode = NULL');
        $this->addSql('ALTER TABLE balance_authorized_user ALTER pincode TYPE VARCHAR(4)');
    }
}

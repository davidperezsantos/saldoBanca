<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nomenclador de monedas (balance_currency) — qué códigos de moneda opera el sistema. Se
 * pre-siembra con las 4 monedas ya usadas hoy en los selects hardcodeados del panel Admin
 * (USD, EUR, VES, COP) marcadas como activas, para no cambiar el comportamiento actual de ningún
 * formulario existente. EUR es la moneda base del sistema (env CURRENCY).
 */
final class Version20260716170703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nomenclador de monedas: tabla balance_currency, pre-sembrada con USD/EUR/VES/COP activas.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE balance_currency (
              code VARCHAR(3) NOT NULL,
              name VARCHAR(100) NOT NULL,
              symbol VARCHAR(5) DEFAULT NULL,
              isActive BOOLEAN NOT NULL,
              id UUID NOT NULL,
              createdAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updateAt TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42D6398F77153098 ON balance_currency (code)');

        $this->addSql(<<<'SQL'
            INSERT INTO balance_currency (id, code, name, symbol, isActive, createdAt, updateAt)
            VALUES
              (gen_random_uuid(), 'EUR', 'Euro', '€', true, now(), now()),
              (gen_random_uuid(), 'USD', 'Dólar estadounidense', '$', true, now(), now()),
              (gen_random_uuid(), 'VES', 'Bolívar venezolano', 'Bs', true, now(), now()),
              (gen_random_uuid(), 'COP', 'Peso colombiano', '$', true, now(), now())
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE balance_currency');
    }
}

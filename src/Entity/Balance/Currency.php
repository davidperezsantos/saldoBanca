<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\CurrencyRepository;

/**
 * Nomenclador de monedas: qué códigos de moneda opera el sistema (isActive) y con qué nombre/
 * símbolo se muestran. Los selects de moneda del panel Admin (recarga, transferencia, cuentas) y
 * el listado de tasas de cambio solo deben ofrecer/mostrar las monedas marcadas como activas.
 * Las columnas `currency` de Account/Recharge/Transfer/etc. siguen siendo texto libre (no FK) —
 * este catálogo valida/filtra, no reemplaza esas columnas.
 */
#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'balance_currency')]
#[ORM\HasLifecycleCallbacks]
class Currency extends BaseEntity
{
    #[ORM\Column(length: 3, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $symbol = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(?string $symbol): static
    {
        $this->symbol = $symbol;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}

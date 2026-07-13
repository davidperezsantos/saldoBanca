<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\ExchangeRateSnapshotRepository;

#[ORM\Entity(repositoryClass: ExchangeRateSnapshotRepository::class)]
#[ORM\Table(name: 'balance_exchange_rate_snapshot')]
#[ORM\HasLifecycleCallbacks]
class ExchangeRateSnapshot extends BaseEntity
{
    #[ORM\Column(length: 10)]
    private ?string $base = null;

    #[ORM\Column(type: 'json')]
    private array $rates = [];

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?string $timestampApi = null;

    public function getBase(): ?string
    {
        return $this->base;
    }

    public function setBase(string $base): static
    {
        $this->base = $base;
        return $this;
    }

    public function getRates(): array
    {
        return $this->rates;
    }

    public function setRates(array $rates): static
    {
        $this->rates = $rates;
        return $this;
    }

    public function getTimestampApi(): ?string
    {
        return $this->timestampApi;
    }

    public function setTimestampApi(?string $timestampApi): static
    {
        $this->timestampApi = $timestampApi;
        return $this;
    }
}

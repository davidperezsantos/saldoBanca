<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\ExchangeRateRepository;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'balance_exchange_rate')]
#[ORM\HasLifecycleCallbacks]
class ExchangeRate extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: ExchangeRateProvider::class, inversedBy: 'exchangeRates')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ExchangeRateProvider $provider = null;

    #[ORM\Column(length: 10)]
    private ?string $fromCurrency = null;

    #[ORM\Column(length: 10)]
    private ?string $toCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8)]
    private ?string $rate = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 8, nullable: true)]
    private ?string $inverseRate = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $fetchedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function getProvider(): ?ExchangeRateProvider
    {
        return $this->provider;
    }

    public function setProvider(?ExchangeRateProvider $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getFromCurrency(): ?string
    {
        return $this->fromCurrency;
    }

    public function setFromCurrency(string $fromCurrency): static
    {
        $this->fromCurrency = $fromCurrency;
        return $this;
    }

    public function getToCurrency(): ?string
    {
        return $this->toCurrency;
    }

    public function setToCurrency(string $toCurrency): static
    {
        $this->toCurrency = $toCurrency;
        return $this;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;
        return $this;
    }

    public function getInverseRate(): ?string
    {
        return $this->inverseRate;
    }

    public function setInverseRate(?string $inverseRate): static
    {
        $this->inverseRate = $inverseRate;
        return $this;
    }

    public function getFetchedAt(): ?\DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTimeImmutable $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;
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

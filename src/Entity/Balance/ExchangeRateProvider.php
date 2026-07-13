<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\Balance\ExchangeRateProviderRepository;

#[ORM\Entity(repositoryClass: ExchangeRateProviderRepository::class)]
#[ORM\Table(name: 'balance_exchange_rate_provider')]
#[ORM\HasLifecycleCallbacks]
class ExchangeRateProvider extends BaseEntity
{
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiUrl = null;

    #[ORM\Column(name: 'api_key', length: 255, nullable: true)]
    private ?string $apiKey = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(name: 'auth_type', length: 20, nullable: true)]
    private ?string $authType = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active';

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = false;

    #[ORM\OneToMany(mappedBy: 'provider', targetEntity: ExchangeRate::class)]
    private Collection $exchangeRates;

    public function __construct()
    {
        parent::__construct();
        $this->exchangeRates = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getApiUrl(): ?string
    {
        return $this->apiUrl;
    }

    public function setApiUrl(?string $apiUrl): static
    {
        $this->apiUrl = $apiUrl;
        return $this;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey(?string $apiKey): static
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getAuthType(): ?string
    {
        return $this->authType;
    }

    public function setAuthType(?string $authType): static
    {
        $this->authType = $authType;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function getExchangeRates(): Collection
    {
        return $this->exchangeRates;
    }

    public function addExchangeRate(ExchangeRate $exchangeRate): static
    {
        if (!$this->exchangeRates->contains($exchangeRate)) {
            $this->exchangeRates->add($exchangeRate);
            $exchangeRate->setProvider($this);
        }
        return $this;
    }

    public function removeExchangeRate(ExchangeRate $exchangeRate): static
    {
        if ($this->exchangeRates->removeElement($exchangeRate)) {
            if ($exchangeRate->getProvider() === $this) {
                $exchangeRate->setProvider(null);
            }
        }
        return $this;
    }
}

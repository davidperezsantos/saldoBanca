<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\BusinessPayoutAccountRepository;

/**
 * Cuenta bancaria/de pago real de un negocio (accountType=business) — a dónde transferirle el
 * dinero cuando se liquida una conciliación. Un negocio puede tener varias, una por moneda (ver
 * BusinessReconciliation::payoutAccountBase/payoutAccountSecondary — el negocio elige entre estas
 * al aprobar la conciliación en la página pública, una por cada moneda del split si aplica).
 */
#[ORM\Entity(repositoryClass: BusinessPayoutAccountRepository::class)]
#[ORM\Table(name: 'balance_business_payout_account')]
#[ORM\HasLifecycleCallbacks]
class BusinessPayoutAccount extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'account_id', nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(length: 100)]
    private string $alias = '';

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(length: 50)]
    private string $accountNumber = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $swift = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $accountHolder = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): static
    {
        $this->alias = $alias;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getBankName(): ?string
    {
        return $this->bankName;
    }

    public function setBankName(?string $bankName): static
    {
        $this->bankName = $bankName;
        return $this;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;
        return $this;
    }

    public function getSwift(): ?string
    {
        return $this->swift;
    }

    public function setSwift(?string $swift): static
    {
        $this->swift = $swift;
        return $this;
    }

    public function getAccountHolder(): ?string
    {
        return $this->accountHolder;
    }

    public function setAccountHolder(?string $accountHolder): static
    {
        $this->accountHolder = $accountHolder;
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

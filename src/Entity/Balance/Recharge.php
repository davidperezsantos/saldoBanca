<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\RechargeRepository;

#[ORM\Entity(repositoryClass: RechargeRepository::class)]
#[ORM\Table(name: 'balance_recharge')]
#[ORM\HasLifecycleCallbacks]
class Recharge extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'recharges')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 20)]
    private ?string $rechargeType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(name: 'original_amount', type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(name: 'original_currency', length: 3, nullable: true)]
    private ?string $originalCurrency = null;

    #[ORM\Column(name: 'exchange_rate', type: 'decimal', precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalSystem = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorizedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transactionId = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
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

    public function getRechargeType(): ?string
    {
        return $this->rechargeType;
    }

    public function setRechargeType(string $rechargeType): static
    {
        $this->rechargeType = $rechargeType;
        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): static
    {
        $this->referenceNumber = $referenceNumber;
        return $this;
    }

    public function getOriginalAmount(): ?string
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(?string $originalAmount): static
    {
        $this->originalAmount = $originalAmount;
        return $this;
    }

    public function getOriginalCurrency(): ?string
    {
        return $this->originalCurrency;
    }

    public function setOriginalCurrency(?string $originalCurrency): static
    {
        $this->originalCurrency = $originalCurrency;
        return $this;
    }

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;
        return $this;
    }

    public function getExternalSystem(): ?string
    {
        return $this->externalSystem;
    }

    public function setExternalSystem(?string $externalSystem): static
    {
        $this->externalSystem = $externalSystem;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getAuthorizedBy(): ?string
    {
        return $this->authorizedBy;
    }

    public function setAuthorizedBy(?string $authorizedBy): static
    {
        $this->authorizedBy = $authorizedBy;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): static
    {
        $this->transactionId = $transactionId;
        return $this;
    }
}

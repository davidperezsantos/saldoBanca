<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\AccountBalanceRepository;

#[ORM\Entity(repositoryClass: AccountBalanceRepository::class)]
#[ORM\Table(name: 'balance_account_balance')]
#[ORM\UniqueConstraint(name: 'unique_account_currency', columns: ['account_id', 'currency'])]
#[ORM\HasLifecycleCallbacks]
class AccountBalance extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'balances')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $availableBalance = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $pendingBalance = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $reservedBalance = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $totalRecharged = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $totalTransferred = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $totalInvoiced = '0.00';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastMovementAt = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
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

    public function getAvailableBalance(): string
    {
        return $this->availableBalance;
    }

    public function setAvailableBalance(string $availableBalance): static
    {
        $this->availableBalance = $availableBalance;
        return $this;
    }

    public function getPendingBalance(): string
    {
        return $this->pendingBalance;
    }

    public function setPendingBalance(string $pendingBalance): static
    {
        $this->pendingBalance = $pendingBalance;
        return $this;
    }

    public function getReservedBalance(): string
    {
        return $this->reservedBalance;
    }

    public function setReservedBalance(string $reservedBalance): static
    {
        $this->reservedBalance = $reservedBalance;
        return $this;
    }

    public function getTotalRecharged(): string
    {
        return $this->totalRecharged;
    }

    public function setTotalRecharged(string $totalRecharged): static
    {
        $this->totalRecharged = $totalRecharged;
        return $this;
    }

    public function getTotalTransferred(): string
    {
        return $this->totalTransferred;
    }

    public function setTotalTransferred(string $totalTransferred): static
    {
        $this->totalTransferred = $totalTransferred;
        return $this;
    }

    public function getTotalInvoiced(): string
    {
        return $this->totalInvoiced;
    }

    public function setTotalInvoiced(string $totalInvoiced): static
    {
        $this->totalInvoiced = $totalInvoiced;
        return $this;
    }

    public function getLastMovementAt(): ?\DateTimeImmutable
    {
        return $this->lastMovementAt;
    }

    public function setLastMovementAt(?\DateTimeImmutable $lastMovementAt): static
    {
        $this->lastMovementAt = $lastMovementAt;
        return $this;
    }
}

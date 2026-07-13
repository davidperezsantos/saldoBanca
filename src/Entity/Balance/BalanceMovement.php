<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\BalanceMovementRepository;

#[ORM\Entity(repositoryClass: BalanceMovementRepository::class)]
#[ORM\Table(name: 'balance_balance_movement')]
#[ORM\HasLifecycleCallbacks]
class BalanceMovement extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'movements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(length: 30)]
    private ?string $movementType = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $balanceBefore = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $balanceAfter = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referenceId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $performedBy = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getMovementType(): ?string
    {
        return $this->movementType;
    }

    public function setMovementType(string $movementType): static
    {
        $this->movementType = $movementType;
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

    public function getBalanceBefore(): ?string
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(string $balanceBefore): static
    {
        $this->balanceBefore = $balanceBefore;
        return $this;
    }

    public function getBalanceAfter(): ?string
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(string $balanceAfter): static
    {
        $this->balanceAfter = $balanceAfter;
        return $this;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): static
    {
        $this->referenceType = $referenceType;
        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getPerformedBy(): ?string
    {
        return $this->performedBy;
    }

    public function setPerformedBy(?string $performedBy): static
    {
        $this->performedBy = $performedBy;
        return $this;
    }
}

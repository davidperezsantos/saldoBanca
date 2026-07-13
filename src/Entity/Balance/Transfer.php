<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\TransferRepository;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'balance_transfer')]
#[ORM\HasLifecycleCallbacks]
class Transfer extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $originAccount = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $destinationAccount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorizedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $fee = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $originAccountNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $destAccountNumber = null;

    public function getOriginAccount(): ?Account
    {
        return $this->originAccount;
    }

    public function setOriginAccount(?Account $originAccount): static
    {
        $this->originAccount = $originAccount;
        return $this;
    }

    public function getDestinationAccount(): ?Account
    {
        return $this->destinationAccount;
    }

    public function setDestinationAccount(?Account $destinationAccount): static
    {
        $this->destinationAccount = $destinationAccount;
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

    public function getFee(): ?string
    {
        return $this->fee;
    }

    public function setFee(?string $fee): static
    {
        $this->fee = $fee;
        return $this;
    }

    public function getOriginAccountNumber(): ?string
    {
        return $this->originAccountNumber;
    }

    public function setOriginAccountNumber(?string $originAccountNumber): static
    {
        $this->originAccountNumber = $originAccountNumber;
        return $this;
    }

    public function getDestAccountNumber(): ?string
    {
        return $this->destAccountNumber;
    }

    public function setDestAccountNumber(?string $destAccountNumber): static
    {
        $this->destAccountNumber = $destAccountNumber;
        return $this;
    }
}

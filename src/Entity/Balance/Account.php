<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\AccountRepository;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'balance_account')]
#[ORM\HasLifecycleCallbacks]
class Account extends BaseEntity
{
    #[ORM\Column(length: 20, unique: true)]
    private ?string $accountNumber = null;

    #[ORM\Column(length: 20)]
    private ?string $accountType = null;

    #[ORM\Column(length: 255)]
    private ?string $businessName = null;

    #[ORM\Column(length: 20)]
    private ?string $documentType = null;

    #[ORM\Column(length: 20)]
    private ?string $documentNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 10)]
    private string $defaultCurrency = 'USD';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $creditLimit = '0.00';

    #[ORM\Column(type: 'boolean')]
    private bool $allowTransfers = true;

    #[ORM\Column(type: 'boolean')]
    private bool $allowAuthorizedUsers = true;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'account')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: AccountBalance::class, cascade: ['persist', 'remove'])]
    private Collection $balances;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: AuthorizedUser::class, cascade: ['persist', 'remove'])]
    private Collection $authorizedUsers;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: Recharge::class, cascade: ['persist'])]
    private Collection $recharges;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: BalanceMovement::class, cascade: ['persist'])]
    private Collection $movements;

    public function __construct()
    {
        parent::__construct();
        $this->balances = new ArrayCollection();
        $this->authorizedUsers = new ArrayCollection();
        $this->recharges = new ArrayCollection();
        $this->movements = new ArrayCollection();
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;
        return $this;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function setAccountType(string $accountType): static
    {
        $this->accountType = $accountType;
        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): static
    {
        $this->businessName = $businessName;
        return $this;
    }

    public function getDocumentType(): ?string
    {
        return $this->documentType;
    }

    public function setDocumentType(string $documentType): static
    {
        $this->documentType = $documentType;
        return $this;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(string $documentNumber): static
    {
        $this->documentNumber = $documentNumber;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
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

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function setDefaultCurrency(string $defaultCurrency): static
    {
        $this->defaultCurrency = $defaultCurrency;
        return $this;
    }

    public function getCreditLimit(): string
    {
        return $this->creditLimit;
    }

    public function setCreditLimit(string $creditLimit): static
    {
        $this->creditLimit = $creditLimit;
        return $this;
    }

    public function isAllowTransfers(): bool
    {
        return $this->allowTransfers;
    }

    public function setAllowTransfers(bool $allowTransfers): static
    {
        $this->allowTransfers = $allowTransfers;
        return $this;
    }

    public function isAllowAuthorizedUsers(): bool
    {
        return $this->allowAuthorizedUsers;
    }

    public function setAllowAuthorizedUsers(bool $allowAuthorizedUsers): static
    {
        $this->allowAuthorizedUsers = $allowAuthorizedUsers;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, AccountBalance>
     */
    public function getBalances(): Collection
    {
        return $this->balances;
    }

    public function addBalance(AccountBalance $balance): static
    {
        if (!$this->balances->contains($balance)) {
            $this->balances->add($balance);
            $balance->setAccount($this);
        }

        return $this;
    }

    public function removeBalance(AccountBalance $balance): static
    {
        if ($this->balances->removeElement($balance)) {
            if ($balance->getAccount() === $this) {
                $balance->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AuthorizedUser>
     */
    public function getAuthorizedUsers(): Collection
    {
        return $this->authorizedUsers;
    }

    public function addAuthorizedUser(AuthorizedUser $authorizedUser): static
    {
        if (!$this->authorizedUsers->contains($authorizedUser)) {
            $this->authorizedUsers->add($authorizedUser);
            $authorizedUser->setAccount($this);
        }

        return $this;
    }

    public function removeAuthorizedUser(AuthorizedUser $authorizedUser): static
    {
        if ($this->authorizedUsers->removeElement($authorizedUser)) {
            if ($authorizedUser->getAccount() === $this) {
                $authorizedUser->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Recharge>
     */
    public function getRecharges(): Collection
    {
        return $this->recharges;
    }

    public function addRecharge(Recharge $recharge): static
    {
        if (!$this->recharges->contains($recharge)) {
            $this->recharges->add($recharge);
            $recharge->setAccount($this);
        }

        return $this;
    }

    public function removeRecharge(Recharge $recharge): static
    {
        if ($this->recharges->removeElement($recharge)) {
            if ($recharge->getAccount() === $this) {
                $recharge->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BalanceMovement>
     */
    public function getMovements(): Collection
    {
        return $this->movements;
    }

    public function addMovement(BalanceMovement $movement): static
    {
        if (!$this->movements->contains($movement)) {
            $this->movements->add($movement);
            $movement->setAccount($this);
        }

        return $this;
    }

    public function removeMovement(BalanceMovement $movement): static
    {
        if ($this->movements->removeElement($movement)) {
            if ($movement->getAccount() === $this) {
                $movement->setAccount(null);
            }
        }

        return $this;
    }
}

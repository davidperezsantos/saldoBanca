<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\AuthorizedUserRepository;

#[ORM\Entity(repositoryClass: AuthorizedUserRepository::class)]
#[ORM\Table(name: 'balance_authorized_user')]
#[ORM\HasLifecycleCallbacks]
class AuthorizedUser extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'authorizedUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(length: 255)]
    private ?string $userName = null;

    #[ORM\Column(length: 255)]
    private ?string $userEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $userPhone = null;

    #[ORM\Column(length: 20)]
    private ?string $documentType = null;

    #[ORM\Column(length: 20)]
    private ?string $documentNumber = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $maxAmount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $dailyLimit = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $monthlyLimit = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $usedToday = '0.00';

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $usedThisMonth = '0.00';

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $pinCode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dailyResetAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $monthlyResetAt = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(string $userName): static
    {
        $this->userName = $userName;
        return $this;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function setUserEmail(string $userEmail): static
    {
        $this->userEmail = $userEmail;
        return $this;
    }

    public function getUserPhone(): ?string
    {
        return $this->userPhone;
    }

    public function setUserPhone(?string $userPhone): static
    {
        $this->userPhone = $userPhone;
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

    public function getMaxAmount(): ?string
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(?string $maxAmount): static
    {
        $this->maxAmount = $maxAmount;
        return $this;
    }

    public function getDailyLimit(): ?string
    {
        return $this->dailyLimit;
    }

    public function setDailyLimit(?string $dailyLimit): static
    {
        $this->dailyLimit = $dailyLimit;
        return $this;
    }

    public function getMonthlyLimit(): ?string
    {
        return $this->monthlyLimit;
    }

    public function setMonthlyLimit(?string $monthlyLimit): static
    {
        $this->monthlyLimit = $monthlyLimit;
        return $this;
    }

    public function getUsedToday(): string
    {
        return $this->usedToday;
    }

    public function setUsedToday(string $usedToday): static
    {
        $this->usedToday = $usedToday;
        return $this;
    }

    public function getUsedThisMonth(): string
    {
        return $this->usedThisMonth;
    }

    public function setUsedThisMonth(string $usedThisMonth): static
    {
        $this->usedThisMonth = $usedThisMonth;
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

    public function getPinCode(): ?string
    {
        return $this->pinCode;
    }

    public function setPinCode(?string $pinCode): static
    {
        $this->pinCode = $pinCode;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }

    public function getDailyResetAt(): ?\DateTimeImmutable
    {
        return $this->dailyResetAt;
    }

    public function setDailyResetAt(?\DateTimeImmutable $dailyResetAt): static
    {
        $this->dailyResetAt = $dailyResetAt;
        return $this;
    }

    public function getMonthlyResetAt(): ?\DateTimeImmutable
    {
        return $this->monthlyResetAt;
    }

    public function setMonthlyResetAt(?\DateTimeImmutable $monthlyResetAt): static
    {
        $this->monthlyResetAt = $monthlyResetAt;
        return $this;
    }
}

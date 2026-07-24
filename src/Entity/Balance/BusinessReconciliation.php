<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\BusinessReconciliationRepository;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Conciliación de facturas de un negocio: agrupa sus facturas `paid` de un rango de fechas para que
 * el negocio las apruebe (por link con `approvalToken`, sin login) y luego un admin las apruebe y
 * finalmente liquide (pague) el total al negocio. `status` avanza
 * pending_business -> approved_business -> approved_admin -> settled, con rejected_business /
 * rejected_admin como terminales alternos. El histórico de cada paso vive en
 * BusinessReconciliationEvent, no acá.
 */
#[ORM\Entity(repositoryClass: BusinessReconciliationRepository::class)]
#[ORM\Table(name: 'balance_business_reconciliation')]
#[ORM\HasLifecycleCallbacks]
class BusinessReconciliation extends BaseEntity implements PasswordAuthenticatedUserInterface
{
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'business_account_id', nullable: false)]
    private ?Account $businessAccount = null;

    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $reconciliationNumber = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $periodStart = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $periodEnd = null;

    #[ORM\Column(type: 'integer')]
    private int $invoiceCount = 0;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 20)]
    private string $status = 'pending_business';

    #[ORM\Column(length: 64, unique: true)]
    private ?string $approvalToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $businessApprovedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $businessApprovedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $adminApprovedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminApprovedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rejectedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $settledBy = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $settlementMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $settlementReference = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $settlementSecondaryMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $settlementSecondaryReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $settlementNotes = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $taxPercent = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $taxAmount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $netAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $settlementBaseCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $settlementBaseAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $settlementSecondaryCurrency = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $settlementSecondaryAmount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 4, nullable: true)]
    private ?string $settlementExchangeRate = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $settlementBasePercent = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $settlementSecondaryPercent = null;

    /**
     * Cuentas reales elegidas por el propio negocio al aprobar en la página pública (ver
     * BusinessReconciliationService::approveByBusiness) — una por moneda si hay split. El admin
     * las usa para saber a dónde transferir al liquidar (settle()); no las elige él.
     */
    #[ORM\ManyToOne(targetEntity: BusinessPayoutAccount::class)]
    #[ORM\JoinColumn(name: 'payout_account_base_id', nullable: true)]
    private ?BusinessPayoutAccount $payoutAccountBase = null;

    #[ORM\ManyToOne(targetEntity: BusinessPayoutAccount::class)]
    #[ORM\JoinColumn(name: 'payout_account_secondary_id', nullable: true)]
    private ?BusinessPayoutAccount $payoutAccountSecondary = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $approvalPinHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvalPinExpiresAt = null;

    #[ORM\Column(type: 'integer')]
    private int $approvalPinAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvalPinRequestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvalPinVerifiedAt = null;

    #[ORM\OneToMany(mappedBy: 'reconciliation', targetEntity: InvoicePayment::class)]
    private Collection $invoices;

    public function __construct()
    {
        parent::__construct();
        $this->invoices = new ArrayCollection();
    }

    public function getBusinessAccount(): ?Account
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount(Account $businessAccount): static
    {
        $this->businessAccount = $businessAccount;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getInvoiceCount(): int
    {
        return $this->invoiceCount;
    }

    public function setInvoiceCount(int $invoiceCount): static
    {
        $this->invoiceCount = $invoiceCount;
        return $this;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
        return $this;
    }

    public function getTaxPercent(): ?string
    {
        return $this->taxPercent;
    }

    public function setTaxPercent(?string $taxPercent): static
    {
        $this->taxPercent = $taxPercent;
        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getNetAmount(): ?string
    {
        return $this->netAmount;
    }

    public function setNetAmount(?string $netAmount): static
    {
        $this->netAmount = $netAmount;
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

    public function getApprovalToken(): ?string
    {
        return $this->approvalToken;
    }

    public function setApprovalToken(string $approvalToken): static
    {
        $this->approvalToken = $approvalToken;
        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getBusinessApprovedAt(): ?\DateTimeImmutable
    {
        return $this->businessApprovedAt;
    }

    public function setBusinessApprovedAt(?\DateTimeImmutable $businessApprovedAt): static
    {
        $this->businessApprovedAt = $businessApprovedAt;
        return $this;
    }

    public function getBusinessApprovedBy(): ?string
    {
        return $this->businessApprovedBy;
    }

    public function setBusinessApprovedBy(?string $businessApprovedBy): static
    {
        $this->businessApprovedBy = $businessApprovedBy;
        return $this;
    }

    public function getAdminApprovedAt(): ?\DateTimeImmutable
    {
        return $this->adminApprovedAt;
    }

    public function setAdminApprovedAt(?\DateTimeImmutable $adminApprovedAt): static
    {
        $this->adminApprovedAt = $adminApprovedAt;
        return $this;
    }

    public function getAdminApprovedBy(): ?string
    {
        return $this->adminApprovedBy;
    }

    public function setAdminApprovedBy(?string $adminApprovedBy): static
    {
        $this->adminApprovedBy = $adminApprovedBy;
        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }

    public function getRejectedBy(): ?string
    {
        return $this->rejectedBy;
    }

    public function setRejectedBy(?string $rejectedBy): static
    {
        $this->rejectedBy = $rejectedBy;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getSettledAt(): ?\DateTimeImmutable
    {
        return $this->settledAt;
    }

    public function setSettledAt(?\DateTimeImmutable $settledAt): static
    {
        $this->settledAt = $settledAt;
        return $this;
    }

    public function getSettledBy(): ?string
    {
        return $this->settledBy;
    }

    public function setSettledBy(?string $settledBy): static
    {
        $this->settledBy = $settledBy;
        return $this;
    }

    public function getSettlementMethod(): ?string
    {
        return $this->settlementMethod;
    }

    public function setSettlementMethod(?string $settlementMethod): static
    {
        $this->settlementMethod = $settlementMethod;
        return $this;
    }

    public function getSettlementReference(): ?string
    {
        return $this->settlementReference;
    }

    public function setSettlementReference(?string $settlementReference): static
    {
        $this->settlementReference = $settlementReference;
        return $this;
    }

    public function getSettlementSecondaryMethod(): ?string
    {
        return $this->settlementSecondaryMethod;
    }

    public function setSettlementSecondaryMethod(?string $settlementSecondaryMethod): static
    {
        $this->settlementSecondaryMethod = $settlementSecondaryMethod;
        return $this;
    }

    public function getSettlementSecondaryReference(): ?string
    {
        return $this->settlementSecondaryReference;
    }

    public function setSettlementSecondaryReference(?string $settlementSecondaryReference): static
    {
        $this->settlementSecondaryReference = $settlementSecondaryReference;
        return $this;
    }

    public function getSettlementNotes(): ?string
    {
        return $this->settlementNotes;
    }

    public function setSettlementNotes(?string $settlementNotes): static
    {
        $this->settlementNotes = $settlementNotes;
        return $this;
    }

    public function getSettlementBaseCurrency(): ?string
    {
        return $this->settlementBaseCurrency;
    }

    public function setSettlementBaseCurrency(?string $settlementBaseCurrency): static
    {
        $this->settlementBaseCurrency = $settlementBaseCurrency;
        return $this;
    }

    public function getSettlementBaseAmount(): ?string
    {
        return $this->settlementBaseAmount;
    }

    public function setSettlementBaseAmount(?string $settlementBaseAmount): static
    {
        $this->settlementBaseAmount = $settlementBaseAmount;
        return $this;
    }

    public function getSettlementSecondaryCurrency(): ?string
    {
        return $this->settlementSecondaryCurrency;
    }

    public function setSettlementSecondaryCurrency(?string $settlementSecondaryCurrency): static
    {
        $this->settlementSecondaryCurrency = $settlementSecondaryCurrency;
        return $this;
    }

    public function getSettlementSecondaryAmount(): ?string
    {
        return $this->settlementSecondaryAmount;
    }

    public function setSettlementSecondaryAmount(?string $settlementSecondaryAmount): static
    {
        $this->settlementSecondaryAmount = $settlementSecondaryAmount;
        return $this;
    }

    public function getSettlementExchangeRate(): ?string
    {
        return $this->settlementExchangeRate;
    }

    public function setSettlementExchangeRate(?string $settlementExchangeRate): static
    {
        $this->settlementExchangeRate = $settlementExchangeRate;
        return $this;
    }

    public function getSettlementBasePercent(): ?string
    {
        return $this->settlementBasePercent;
    }

    public function setSettlementBasePercent(?string $settlementBasePercent): static
    {
        $this->settlementBasePercent = $settlementBasePercent;
        return $this;
    }

    public function getSettlementSecondaryPercent(): ?string
    {
        return $this->settlementSecondaryPercent;
    }

    public function setSettlementSecondaryPercent(?string $settlementSecondaryPercent): static
    {
        $this->settlementSecondaryPercent = $settlementSecondaryPercent;
        return $this;
    }

    public function getPayoutAccountBase(): ?BusinessPayoutAccount
    {
        return $this->payoutAccountBase;
    }

    public function setPayoutAccountBase(?BusinessPayoutAccount $payoutAccountBase): static
    {
        $this->payoutAccountBase = $payoutAccountBase;
        return $this;
    }

    public function getPayoutAccountSecondary(): ?BusinessPayoutAccount
    {
        return $this->payoutAccountSecondary;
    }

    public function setPayoutAccountSecondary(?BusinessPayoutAccount $payoutAccountSecondary): static
    {
        $this->payoutAccountSecondary = $payoutAccountSecondary;
        return $this;
    }

    public function getReconciliationNumber(): ?string
    {
        return $this->reconciliationNumber;
    }

    public function setReconciliationNumber(?string $reconciliationNumber): static
    {
        $this->reconciliationNumber = $reconciliationNumber;
        return $this;
    }

    public function getApprovalPinHash(): ?string
    {
        return $this->approvalPinHash;
    }

    public function setApprovalPinHash(?string $approvalPinHash): static
    {
        $this->approvalPinHash = $approvalPinHash;
        return $this;
    }

    public function getApprovalPinExpiresAt(): ?\DateTimeImmutable
    {
        return $this->approvalPinExpiresAt;
    }

    public function setApprovalPinExpiresAt(?\DateTimeImmutable $approvalPinExpiresAt): static
    {
        $this->approvalPinExpiresAt = $approvalPinExpiresAt;
        return $this;
    }

    public function getApprovalPinAttempts(): int
    {
        return $this->approvalPinAttempts;
    }

    public function setApprovalPinAttempts(int $approvalPinAttempts): static
    {
        $this->approvalPinAttempts = $approvalPinAttempts;
        return $this;
    }

    public function getApprovalPinRequestedAt(): ?\DateTimeImmutable
    {
        return $this->approvalPinRequestedAt;
    }

    public function setApprovalPinRequestedAt(?\DateTimeImmutable $approvalPinRequestedAt): static
    {
        $this->approvalPinRequestedAt = $approvalPinRequestedAt;
        return $this;
    }

    public function getApprovalPinVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->approvalPinVerifiedAt;
    }

    public function setApprovalPinVerifiedAt(?\DateTimeImmutable $approvalPinVerifiedAt): static
    {
        $this->approvalPinVerifiedAt = $approvalPinVerifiedAt;
        return $this;
    }

    /**
     * Puente hacia UserPasswordHasherInterface (mismo mecanismo que AuthorizedUser::pinCode) —
     * no es una contraseña de login, es el hash del PIN de verificación vigente.
     */
    public function getPassword(): ?string
    {
        return $this->approvalPinHash;
    }

    /**
     * @return Collection<int, InvoicePayment>
     */
    public function getInvoices(): Collection
    {
        return $this->invoices;
    }
}

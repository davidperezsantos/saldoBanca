<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use App\Repository\Balance\CommissionSettlementRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Liquidación de la comisión del sistema entre el Administrador (dueño de la cuenta física con el
 * dinero recaudado) y el Super Administrador (a quien le corresponde esa comisión). Distinta de
 * BusinessReconciliation (que es con negocios) — acá el "negocio" es el propio sistema.
 *
 * status: pending_admin_approval -> approved_admin -> pending_settlement -> settled -> closed.
 * El administrador crea y aprueba (confirma el monto), el super administrador asigna la cuenta de
 * pago (ahí el administrador ya puede transferir) y este liquida marcándola settled, y finalmente
 * el super administrador verifica y cierra — solo al cerrar se descuenta definitivamente de la
 * comisión disponible (ver CommissionSettlementService::getAvailableCommission()).
 *
 * Acciones vía /api/v1 (OAuth2 client credentials, sin sesión de usuario) exigen además un PIN de
 * un solo uso enviado por WhatsApp al teléfono del usuario interno que las realiza — mismo patrón
 * y campos que BusinessReconciliation::approvalPin*, pero acá atados a un $pinVerifiedFor (el
 * username que lo verificó) porque las 4 acciones sensibles las puede hacer gente distinta en
 * momentos distintos, a diferencia del negocio externo que aprueba/rechaza una sola vez.
 */
#[ORM\Entity(repositoryClass: CommissionSettlementRepository::class)]
#[ORM\Table(name: 'balance_commission_settlement')]
#[ORM\HasLifecycleCallbacks]
class CommissionSettlement extends BaseEntity implements PasswordAuthenticatedUserInterface
{
    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $settlementNumber = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private string $amount = '0.00';

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(length: 25)]
    private string $status = 'pending_admin_approval';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $adminApprovedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adminApprovedBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $payoutAccountNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $payoutBankName = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $payoutAccountHolder = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $accountAssignedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountAssignedBy = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $settlementMethod = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $settlementReference = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $settledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $settledBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $closedBy = null;

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

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $pinVerifiedFor = null;

    public function getPassword(): ?string
    {
        return $this->approvalPinHash;
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

    public function getPinVerifiedFor(): ?string
    {
        return $this->pinVerifiedFor;
    }

    public function setPinVerifiedFor(?string $pinVerifiedFor): static
    {
        $this->pinVerifiedFor = $pinVerifiedFor;
        return $this;
    }

    public function getSettlementNumber(): ?string
    {
        return $this->settlementNumber;
    }

    public function setSettlementNumber(?string $settlementNumber): static
    {
        $this->settlementNumber = $settlementNumber;
        return $this;
    }

    public function getAmount(): string
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

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;
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

    public function getPayoutAccountNumber(): ?string
    {
        return $this->payoutAccountNumber;
    }

    public function setPayoutAccountNumber(?string $payoutAccountNumber): static
    {
        $this->payoutAccountNumber = $payoutAccountNumber;
        return $this;
    }

    public function getPayoutBankName(): ?string
    {
        return $this->payoutBankName;
    }

    public function setPayoutBankName(?string $payoutBankName): static
    {
        $this->payoutBankName = $payoutBankName;
        return $this;
    }

    public function getPayoutAccountHolder(): ?string
    {
        return $this->payoutAccountHolder;
    }

    public function setPayoutAccountHolder(?string $payoutAccountHolder): static
    {
        $this->payoutAccountHolder = $payoutAccountHolder;
        return $this;
    }

    public function getAccountAssignedAt(): ?\DateTimeImmutable
    {
        return $this->accountAssignedAt;
    }

    public function setAccountAssignedAt(?\DateTimeImmutable $accountAssignedAt): static
    {
        $this->accountAssignedAt = $accountAssignedAt;
        return $this;
    }

    public function getAccountAssignedBy(): ?string
    {
        return $this->accountAssignedBy;
    }

    public function setAccountAssignedBy(?string $accountAssignedBy): static
    {
        $this->accountAssignedBy = $accountAssignedBy;
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

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): static
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    public function getClosedBy(): ?string
    {
        return $this->closedBy;
    }

    public function setClosedBy(?string $closedBy): static
    {
        $this->closedBy = $closedBy;
        return $this;
    }
}

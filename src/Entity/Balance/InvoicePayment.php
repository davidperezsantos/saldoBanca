<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\InvoicePaymentRepository;

#[ORM\Entity(repositoryClass: InvoicePaymentRepository::class)]
#[ORM\Table(name: 'balance_invoice_payment')]
#[ORM\HasLifecycleCallbacks]
class InvoicePayment extends BaseEntity
{
    /**
     * El cliente: a quien se le descuenta el saldo al pagar la factura (processPayment()).
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    /**
     * El negocio que realizó la operación/venta — opcional, puramente informativo (no se le
     * mueve saldo). Solo cuentas accountType=business, a diferencia de $account (el cliente),
     * que puede ser cualquier tipo de cuenta.
     */
    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'business_account_id', nullable: true)]
    private ?Account $businessAccount = null;

    #[ORM\Column(length: 50)]
    private ?string $invoiceNumber = null;

    /**
     * Código interno correlativo, generado por DocumentNumberService al crear la factura —
     * distinto de invoiceNumber, que lo asigna el sistema externo y no es único a nivel global.
     */
    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $receiptNumber = null;

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $invoiceDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $taxAmount = null;

    #[ORM\Column(type: 'decimal', precision: 18, scale: 2)]
    private ?string $totalAmount = null;

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    /**
     * Monto/moneda tal como los tecleó quien creó la factura, antes de convertirlos a la moneda
     * base del sistema (igual que Recharge/Transfer) — null si ya se pidió directamente en la
     * moneda base.
     */
    #[ORM\Column(name: 'original_amount', type: 'decimal', precision: 18, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(name: 'original_currency', length: 3, nullable: true)]
    private ?string $originalCurrency = null;

    #[ORM\Column(name: 'exchange_rate', type: 'decimal', precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    #[ORM\Column(length: 20)]
    private string $status = 'pending';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $paymentDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalRef = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalSystem = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $customerCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerName = null;

    /**
     * Cómo se pagó — solo se completa cuando se paga vía AuthorizedService::spend() ('saldo' o
     * 'efectivo'); queda null para el flujo normal (InvoiceService::processPayment(), débito
     * directo del titular).
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    /**
     * La conciliación de negocio (si la hay) que agrupa esta factura — se asigna al crear la
     * conciliación (status pasa a 'conciliando') y se limpia si la conciliación es rechazada.
     */
    #[ORM\ManyToOne(targetEntity: BusinessReconciliation::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(name: 'reconciliation_id', nullable: true, onDelete: 'SET NULL')]
    private ?BusinessReconciliation $reconciliation = null;

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getBusinessAccount(): ?Account
    {
        return $this->businessAccount;
    }

    public function setBusinessAccount(?Account $businessAccount): static
    {
        $this->businessAccount = $businessAccount;
        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getReceiptNumber(): ?string
    {
        return $this->receiptNumber;
    }

    public function setReceiptNumber(?string $receiptNumber): static
    {
        $this->receiptNumber = $receiptNumber;
        return $this;
    }

    public function getInvoiceDate(): ?\DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function setInvoiceDate(\DateTimeImmutable $invoiceDate): static
    {
        $this->invoiceDate = $invoiceDate;
        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;
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

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;
        return $this;
    }

    public function getTotalAmount(): ?string
    {
        return $this->totalAmount;
    }

    public function setTotalAmount(string $totalAmount): static
    {
        $this->totalAmount = $totalAmount;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPaymentDate(): ?\DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(?\DateTimeImmutable $paymentDate): static
    {
        $this->paymentDate = $paymentDate;
        return $this;
    }

    public function getExternalRef(): ?string
    {
        return $this->externalRef;
    }

    public function setExternalRef(?string $externalRef): static
    {
        $this->externalRef = $externalRef;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCustomerCode(): ?string
    {
        return $this->customerCode;
    }

    public function setCustomerCode(?string $customerCode): static
    {
        $this->customerCode = $customerCode;
        return $this;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function setCustomerName(?string $customerName): static
    {
        $this->customerName = $customerName;
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

    public function getReconciliation(): ?BusinessReconciliation
    {
        return $this->reconciliation;
    }

    public function setReconciliation(?BusinessReconciliation $reconciliation): static
    {
        $this->reconciliation = $reconciliation;
        return $this;
    }
}

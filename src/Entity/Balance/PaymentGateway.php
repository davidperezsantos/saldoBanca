<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use App\Entity\LogEntry;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\PaymentGatewayRepository;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Auditada con Gedmo Loggable (ver App\Entity\LogEntry) — `config` queda fuera de
 * #[Gedmo\Versioned] a propósito: guarda el webhook_secret (Fase 3), no debe terminar en la tabla
 * de auditoría en texto plano.
 */
#[ORM\Entity(repositoryClass: PaymentGatewayRepository::class)]
#[ORM\Table(name: 'balance_payment_gateway')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class PaymentGateway extends BaseEntity
{
    #[ORM\Column(length: 100)]
    #[Gedmo\Versioned]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Gedmo\Versioned]
    private ?string $code = null;

    #[ORM\Column(length: 20)]
    #[Gedmo\Versioned]
    private ?string $authType = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $config = null;

    #[ORM\Column(length: 20)]
    #[Gedmo\Versioned]
    private ?string $status = 'active';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean')]
    #[Gedmo\Versioned]
    private bool $isDefault = false;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getAuthType(): ?string
    {
        return $this->authType;
    }

    public function setAuthType(string $authType): static
    {
        $this->authType = $authType;
        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }
}

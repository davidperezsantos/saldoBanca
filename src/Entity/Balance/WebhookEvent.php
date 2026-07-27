<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\WebhookEventRepository;

#[ORM\Entity(repositoryClass: WebhookEventRepository::class)]
#[ORM\Table(name: 'balance_webhook_event')]
#[ORM\HasLifecycleCallbacks]
class WebhookEvent extends BaseEntity
{
    #[ORM\Column(length: 100)]
    private ?string $gatewayCode = null;

    #[ORM\ManyToOne(targetEntity: PaymentGateway::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PaymentGateway $gateway = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: 'boolean')]
    private bool $signatureValid = false;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Recharge::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Recharge $recharge = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function getGatewayCode(): ?string
    {
        return $this->gatewayCode;
    }

    public function setGatewayCode(string $gatewayCode): static
    {
        $this->gatewayCode = $gatewayCode;
        return $this;
    }

    public function getGateway(): ?PaymentGateway
    {
        return $this->gateway;
    }

    public function setGateway(?PaymentGateway $gateway): static
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function isSignatureValid(): bool
    {
        return $this->signatureValid;
    }

    public function setSignatureValid(bool $signatureValid): static
    {
        $this->signatureValid = $signatureValid;
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

    public function getRecharge(): ?Recharge
    {
        return $this->recharge;
    }

    public function setRecharge(?Recharge $recharge): static
    {
        $this->recharge = $recharge;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }
}

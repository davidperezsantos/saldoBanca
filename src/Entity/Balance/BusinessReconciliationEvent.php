<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\BusinessReconciliationEventRepository;

/**
 * Una fila por paso del flujo de conciliación (created, sent, approved_business, rejected_business,
 * approved_admin, rejected_admin, settled) — es el "cuadro histórico" que se muestra en el admin:
 * quién hizo cada paso y cuándo. `createdAt` (de BaseEntity) es la fecha del evento.
 */
#[ORM\Entity(repositoryClass: BusinessReconciliationEventRepository::class)]
#[ORM\Table(name: 'balance_business_reconciliation_event')]
#[ORM\HasLifecycleCallbacks]
class BusinessReconciliationEvent extends BaseEntity
{
    #[ORM\ManyToOne(targetEntity: BusinessReconciliation::class)]
    #[ORM\JoinColumn(name: 'reconciliation_id', nullable: false, onDelete: 'CASCADE')]
    private ?BusinessReconciliation $reconciliation = null;

    #[ORM\Column(length: 30)]
    private ?string $eventType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $performedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function getReconciliation(): ?BusinessReconciliation
    {
        return $this->reconciliation;
    }

    public function setReconciliation(BusinessReconciliation $reconciliation): static
    {
        $this->reconciliation = $reconciliation;
        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }
}

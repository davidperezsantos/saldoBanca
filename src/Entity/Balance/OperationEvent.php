<?php

namespace App\Entity\Balance;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\Balance\OperationEventRepository;

/**
 * Línea de tiempo genérica "quién hizo qué y cuándo" para un registro operativo (factura, recarga,
 * transferencia, consumo de autorizado, etc.), identificado por (entityType, entityId) — no hay FK
 * porque un mismo evento log sirve para varios tipos de entidad distintos. Para conciliaciones de
 * negocio existe su propio log más detallado (BusinessReconciliationEvent, con metadata de
 * liquidación); este es el genérico para todo lo demás, pensado para mostrarse en un diálogo desde
 * el Historial al hacer clic en una fila.
 */
#[ORM\Entity(repositoryClass: OperationEventRepository::class)]
#[ORM\Table(name: 'balance_operation_event')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_operation_event_entity')]
#[ORM\HasLifecycleCallbacks]
class OperationEvent extends BaseEntity
{
    #[ORM\Column(name: 'entity_type', length: 30)]
    private ?string $entityType = null;

    #[ORM\Column(name: 'entity_id', length: 36)]
    private ?string $entityId = null;

    #[ORM\Column(length: 30)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $performedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): static
    {
        $this->entityId = $entityId;
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
}

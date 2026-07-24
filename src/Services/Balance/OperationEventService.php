<?php

namespace App\Services\Balance;

use App\Entity\Balance\OperationEvent;
use App\Repository\Balance\OperationEventRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Registra la línea de tiempo de estados de un registro operativo (factura, recarga, transferencia,
 * consumo de autorizado) para que el Historial pueda mostrar "qué pasó y quién lo hizo" para esa
 * operación puntual, sin importar si tuvo un solo paso o varios.
 */
class OperationEventService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private OperationEventRepository $eventRepository,
    ) {
        parent::__construct($entityManager);
    }

    public function log(string $entityType, string $entityId, string $status, ?string $performedBy, ?string $notes = null): void
    {
        $event = new OperationEvent();
        $event->setEntityType($entityType);
        $event->setEntityId($entityId);
        $event->setStatus($status);
        $event->setPerformedBy($performedBy);
        $event->setNotes($notes);

        $this->persist($event);
        $this->flush();
    }

    /**
     * @return OperationEvent[]
     */
    public function getTimeline(string $entityType, string $entityId): array
    {
        return $this->eventRepository->findByEntity($entityType, $entityId);
    }
}

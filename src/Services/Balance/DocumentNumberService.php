<?php

namespace App\Services\Balance;

use App\Repository\Balance\DocumentSequenceRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Numeración correlativa propia para comprobantes generados por el sistema (recibos de recarga,
 * de transferencia). Distinto de InvoicePayment.invoiceNumber, que lo asigna el sistema externo.
 */
class DocumentNumberService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private DocumentSequenceRepository $sequenceRepository,
    ) {
        parent::__construct($entityManager);
    }

    public function next(string $documentType, string $prefix): string
    {
        $this->ensureSequenceExists($documentType);

        return $this->entityManager->wrapInTransaction(function () use ($documentType, $prefix) {
            $sequence = $this->sequenceRepository->findForUpdate($documentType);
            if (!$sequence) {
                throw new \RuntimeException("Unknown document sequence: {$documentType}");
            }

            $next = (string) ((int) $sequence->getLastValue() + 1);
            $sequence->setLastValue($next);
            $this->flush();

            return $prefix . str_pad($next, 8, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Autocura un tipo de documento que nunca quedó pre-sembrado — ej. una DB "desde cero"
     * bootstrapeada con doctrine:schema:create (docker/entrypoint.sh) en vez de correr las
     * migraciones viejas que insertaban estas filas a mano. INSERT...ON CONFLICT DO NOTHING vía
     * DBAL en vez de persist()/flush() del entity manager a propósito: si dos requests pisan el
     * primerísimo uso del mismo tipo a la vez, un fallo de constraint durante un flush() puede
     * dejar el EntityManager en un estado no reusable por el resto del request — la resolución de
     * conflicto de Postgres es atómica y no necesita capturar nada acá.
     */
    private function ensureSequenceExists(string $documentType): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO balance_document_sequence (documentType, lastValue) VALUES (:type, 0) ON CONFLICT (documentType) DO NOTHING',
            ['type' => $documentType]
        );
    }
}

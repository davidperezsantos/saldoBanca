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
}

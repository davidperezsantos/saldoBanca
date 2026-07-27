<?php

namespace App\Repository\Balance;

use App\Entity\Balance\DocumentSequence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentSequence>
 */
class DocumentSequenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentSequence::class);
    }

    /**
     * Toma un lock pesimista (SELECT ... FOR UPDATE) sobre la fila del contador — debe llamarse
     * dentro de una transacción activa; serializa la asignación de números correlativos entre
     * creaciones concurrentes del mismo tipo de comprobante (mismo patrón que
     * AccountBalanceRepository::findByAccountAndCurrencyForUpdate de la Fase 2).
     */
    public function findForUpdate(string $documentType): ?DocumentSequence
    {
        return $this->find($documentType, LockMode::PESSIMISTIC_WRITE);
    }
}

<?php

namespace App\Repository\Balance;

use App\Entity\Balance\InvoicePayment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvoicePayment>
 */
class InvoicePaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvoicePayment::class);
    }

    public function save(InvoicePayment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(InvoicePayment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Cambia el estado solo si sigue siendo $expectedStatus, en un único UPDATE atómico
     * (evita la ventana de carrera de leer-comparar-escribir en PHP entre dos requests concurrentes).
     * Devuelve true si el cambio se aplicó (es decir, si seguía en $expectedStatus).
     */
    public function markStatusIfCurrent(string $id, string $expectedStatus, string $newStatus): bool
    {
        $affected = $this->getEntityManager()->createQueryBuilder()
            ->update(InvoicePayment::class, 'i')
            ->set('i.status', ':new')
            ->where('i.id = :id')
            ->andWhere('i.status = :expected')
            ->setParameter('new', $newStatus)
            ->setParameter('id', $id)
            ->setParameter('expected', $expectedStatus)
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    /**
     * Facturas de un negocio, pagadas y todavía no incluidas en ninguna conciliación, dentro del
     * rango de fechas — el universo que BusinessReconciliationService::preview()/create() agrupan.
     *
     * @return InvoicePayment[]
     */
    public function findEligibleForReconciliation(
        string $businessAccountId,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd
    ): array {
        return $this->createQueryBuilder('i')
            ->andWhere('i.businessAccount = :businessAccountId')
            ->andWhere('i.status = :status')
            ->andWhere('i.reconciliation IS NULL')
            ->andWhere('i.invoiceDate BETWEEN :start AND :end')
            ->setParameter('businessAccountId', $businessAccountId)
            ->setParameter('status', 'paid')
            ->setParameter('start', $periodStart)
            ->setParameter('end', $periodEnd)
            ->orderBy('i.invoiceDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Asigna una factura a una conciliación y la pasa a 'conciliando', solo si sigue 'paid' (evita
     * incluir una factura que cambió de estado entre el preview y el create). Devuelve true si se
     * aplicó.
     */
    public function assignToReconciliationIfPaid(string $id, string $reconciliationId): bool
    {
        $affected = $this->getEntityManager()->createQueryBuilder()
            ->update(InvoicePayment::class, 'i')
            ->set('i.status', ':new')
            ->set('i.reconciliation', ':reconciliationId')
            ->where('i.id = :id')
            ->andWhere('i.status = :expected')
            ->setParameter('new', 'conciliando')
            ->setParameter('reconciliationId', $reconciliationId)
            ->setParameter('id', $id)
            ->setParameter('expected', 'paid')
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

}

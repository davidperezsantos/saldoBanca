<?php

namespace App\Repository\Balance;

use App\Entity\Balance\BusinessReconciliation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessReconciliation>
 */
class BusinessReconciliationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessReconciliation::class);
    }

    public function findByToken(string $token): ?BusinessReconciliation
    {
        return $this->findOneBy(['approvalToken' => $token]);
    }

    /**
     * Cambia el estado solo si sigue siendo $expectedStatus, en un único UPDATE atómico
     * (evita la ventana de carrera de leer-comparar-escribir entre dos requests concurrentes),
     * mismo patrón que InvoicePaymentRepository::markStatusIfCurrent().
     */
    public function markStatusIfCurrent(string $id, string $expectedStatus, string $newStatus): bool
    {
        $affected = $this->getEntityManager()->createQueryBuilder()
            ->update(BusinessReconciliation::class, 'r')
            ->set('r.status', ':new')
            ->where('r.id = :id')
            ->andWhere('r.status = :expected')
            ->setParameter('new', $newStatus)
            ->setParameter('id', $id)
            ->setParameter('expected', $expectedStatus)
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    /**
     * Comisión retenida por el sistema en conciliaciones ya liquidadas con negocios, desglosada
     * por moneda — un negocio puede facturar en una moneda distinta a la base/secundaria del
     * sistema, así que la comisión puede acumularse en varias monedas a la vez (ver
     * CommissionSettlementService::getAvailableCommission()).
     *
     * @return array<string, string> moneda => suma de taxAmount
     */
    public function sumSettledTaxAmountByCurrency(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.currency as currency, COALESCE(SUM(r.taxAmount), 0) as total')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'settled')
            ->groupBy('r.currency')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['currency']] = (string) $row['total'];
        }

        return $result;
    }

    /**
     * Conciliaciones cuyo período cae dentro del rango pedido — el universo del reporte por
     * negocio (BusinessReconciliationService::buildReconciliationReport()). Sin $status filtra
     * cualquier estado; con $status, solo ese.
     *
     * @return BusinessReconciliation[]
     */
    public function findForReport(\DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, ?string $status = null, ?string $businessAccountId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.periodStart >= :start')
            ->andWhere('r.periodEnd <= :end')
            ->setParameter('start', $periodStart)
            ->setParameter('end', $periodEnd)
            ->orderBy('r.businessAccount', 'ASC')
            ->addOrderBy('r.periodStart', 'ASC');

        if ($status) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }

        if ($businessAccountId) {
            $qb->andWhere('r.businessAccount = :businessAccountId')
                ->setParameter('businessAccountId', $businessAccountId);
        }

        return $qb->getQuery()->getResult();
    }
}

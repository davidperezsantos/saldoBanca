<?php

namespace App\Repository\Balance;

use App\Entity\Balance\CommissionSettlement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommissionSettlement>
 */
class CommissionSettlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionSettlement::class);
    }

    /**
     * Cambia el estado solo si sigue siendo $expectedStatus, en un único UPDATE atómico (evita la
     * ventana de carrera de leer-comparar-escribir entre dos requests concurrentes), mismo patrón
     * que BusinessReconciliationRepository::markStatusIfCurrent().
     */
    public function markStatusIfCurrent(string $id, string $expectedStatus, string $newStatus): bool
    {
        $affected = $this->getEntityManager()->createQueryBuilder()
            ->update(CommissionSettlement::class, 's')
            ->set('s.status', ':new')
            ->where('s.id = :id')
            ->andWhere('s.status = :expected')
            ->setParameter('new', $newStatus)
            ->setParameter('id', $id)
            ->setParameter('expected', $expectedStatus)
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    /**
     * Lo ya reservado contra la comisión de cada moneda por cualquier liquidación existente, sin
     * importar su estado — se reserva desde que se crea, no recién al cerrarse (ver
     * CommissionSettlementService::getAvailableCommission()).
     *
     * @return array<string, string> moneda => suma reservada
     */
    public function sumReservedAmountByCurrency(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.currency as currency, COALESCE(SUM(s.amount), 0) as total')
            ->groupBy('s.currency')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['currency']] = (string) $row['total'];
        }

        return $result;
    }

    /**
     * @return CommissionSettlement[]
     */
    public function findAllFiltered(array $filters): array
    {
        $qb = $this->createQueryBuilder('s')->orderBy('s.createdAt', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('s.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['currency'])) {
            $qb->andWhere('s.currency = :currency')->setParameter('currency', $filters['currency']);
        }

        $qb->setMaxResults($filters['limit'] ?? 50);

        return $qb->getQuery()->getResult();
    }
}

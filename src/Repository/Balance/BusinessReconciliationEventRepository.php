<?php

namespace App\Repository\Balance;

use App\Entity\Balance\BusinessReconciliationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessReconciliationEvent>
 */
class BusinessReconciliationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessReconciliationEvent::class);
    }

    /**
     * @return BusinessReconciliationEvent[]
     */
    public function findByReconciliation(string $reconciliationId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.reconciliation = :id')
            ->setParameter('id', $reconciliationId)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

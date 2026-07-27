<?php

namespace App\Repository\Balance;

use App\Entity\Balance\OperationEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OperationEvent>
 */
class OperationEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OperationEvent::class);
    }

    /**
     * @return OperationEvent[]
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.entityType = :entityType')
            ->andWhere('e.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository\Balance;

use App\Entity\Balance\ExchangeRateSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExchangeRateSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRateSnapshot::class);
    }

    public function findLast(): ?ExchangeRateSnapshot
    {
        return $this->createQueryBuilder('ers')
            ->orderBy('ers.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

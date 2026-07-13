<?php

namespace App\Repository\Balance;

use App\Entity\Balance\ExchangeRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExchangeRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRate::class);
    }

    public function findLatestByPair(string $fromCurrency, string $toCurrency): ?ExchangeRate
    {
        return $this->createQueryBuilder('er')
            ->where('er.fromCurrency = :from')
            ->andWhere('er.toCurrency = :to')
            ->andWhere('er.isActive = true')
            ->setParameter('from', $fromCurrency)
            ->setParameter('to', $toCurrency)
            ->orderBy('er.fetchedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRecentRates(int $limit = 50): array
    {
        return $this->createQueryBuilder('er')
            ->where('er.isActive = true')
            ->orderBy('er.fetchedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

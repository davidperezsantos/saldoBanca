<?php

namespace App\Repository\Balance;

use App\Entity\Balance\Currency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Currency>
 */
class CurrencyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Currency::class);
    }

    /**
     * @return Currency[]
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['code' => 'ASC']);
    }

    /**
     * @return string[]
     */
    public function findActiveCodes(): array
    {
        return array_map(fn(Currency $c) => $c->getCode(), $this->findActive());
    }
}

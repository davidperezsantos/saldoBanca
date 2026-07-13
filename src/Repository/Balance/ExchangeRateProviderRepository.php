<?php

namespace App\Repository\Balance;

use App\Entity\Balance\ExchangeRateProvider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ExchangeRateProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExchangeRateProvider::class);
    }

    public function findByCode(string $code): ?ExchangeRateProvider
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findActive(): array
    {
        return $this->findBy(['status' => 'active']);
    }

    public function findDefault(): ?ExchangeRateProvider
    {
        return $this->findOneBy(['isDefault' => true]);
    }

    public function findOneActive(): ?ExchangeRateProvider
    {
        return $this->findOneBy(['isActive' => true, 'status' => 'active']);
    }
}

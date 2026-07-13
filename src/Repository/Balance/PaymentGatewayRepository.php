<?php

namespace App\Repository\Balance;

use App\Entity\Balance\PaymentGateway;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentGatewayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentGateway::class);
    }

    public function findByCode(string $code): ?PaymentGateway
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findActive(): array
    {
        return $this->findBy(['status' => 'active']);
    }

    public function findDefault(): ?PaymentGateway
    {
        return $this->findOneBy(['isDefault' => true]);
    }
}

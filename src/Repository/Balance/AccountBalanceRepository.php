<?php

namespace App\Repository\Balance;

use App\Entity\Balance\AccountBalance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountBalance>
 */
class AccountBalanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountBalance::class);
    }

    public function save(AccountBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AccountBalance $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByAccountAndCurrency(string $accountId, string $currency): ?AccountBalance
    {
        return $this->createQueryBuilder('ab')
            ->andWhere('ab.account = :accountId')
            ->andWhere('ab.currency = :currency')
            ->setParameter('accountId', $accountId)
            ->setParameter('currency', $currency)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Igual que findByAccountAndCurrency, pero toma un lock pesimista (SELECT ... FOR UPDATE) sobre
     * la fila si ya existe. Debe llamarse dentro de una transacción activa; sirve para serializar
     * lecturas-modificaciones concurrentes del mismo saldo (recargas/transferencias simultáneas).
     */
    public function findByAccountAndCurrencyForUpdate(string $accountId, string $currency): ?AccountBalance
    {
        return $this->createQueryBuilder('ab')
            ->andWhere('ab.account = :accountId')
            ->andWhere('ab.currency = :currency')
            ->setParameter('accountId', $accountId)
            ->setParameter('currency', $currency)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}

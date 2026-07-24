<?php

namespace App\Repository\Balance;

use App\Entity\Balance\Account;
use App\Entity\Balance\BusinessPayoutAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessPayoutAccount>
 */
class BusinessPayoutAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessPayoutAccount::class);
    }

    /**
     * @return BusinessPayoutAccount[]
     */
    public function findByAccount(Account $account): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.account = :account')
            ->setParameter('account', $account)
            ->orderBy('p.alias', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuentas activas del negocio en una moneda dada — para los selects de la aprobación pública
     * y del alta/edición admin.
     *
     * @return BusinessPayoutAccount[]
     */
    public function findActiveByAccountAndCurrency(Account $account, string $currency): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.account = :account')
            ->andWhere('p.currency = :currency')
            ->andWhere('p.isActive = true')
            ->setParameter('account', $account)
            ->setParameter('currency', $currency)
            ->orderBy('p.alias', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository\Balance;

use App\Entity\Balance\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function save(Account $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Account $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.accountNumber = :accountNumber')
            ->setParameter('accountNumber', $accountNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByDocument(string $documentType, string $documentNumber): ?Account
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.documentType = :documentType')
            ->andWhere('a.documentNumber = :documentNumber')
            ->setParameter('documentType', $documentType)
            ->setParameter('documentNumber', $documentNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveAccounts(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
    }
}

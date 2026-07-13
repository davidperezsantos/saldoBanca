<?php

namespace App\Repository\Balance;

use App\Entity\Balance\AuthorizedUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthorizedUser>
 */
class AuthorizedUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthorizedUser::class);
    }

    public function save(AuthorizedUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuthorizedUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByDocumentNumber(string $documentNumber): ?AuthorizedUser
    {
        return $this->createQueryBuilder('au')
            ->andWhere('au.documentNumber = :documentNumber')
            ->andWhere('au.status = :status')
            ->setParameter('documentNumber', $documentNumber)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByAccount(string $accountId): array
    {
        return $this->createQueryBuilder('au')
            ->andWhere('au.account = :accountId')
            ->setParameter('accountId', $accountId)
            ->getQuery()
            ->getResult();
    }
}

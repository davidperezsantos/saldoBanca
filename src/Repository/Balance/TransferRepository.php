<?php

namespace App\Repository\Balance;

use App\Entity\Balance\Account;
use App\Entity\Balance\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transfer>
 */
class TransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    public function save(Transfer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Transfer $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Cambia el estado solo si sigue siendo $expectedStatus, en un único UPDATE atómico
     * (evita la ventana de carrera de leer-comparar-escribir en PHP entre dos requests concurrentes).
     * Devuelve true si el cambio se aplicó (es decir, si seguía en $expectedStatus).
     */
    public function markStatusIfCurrent(string $id, string $expectedStatus, string $newStatus): bool
    {
        $affected = $this->getEntityManager()->createQueryBuilder()
            ->update(Transfer::class, 't')
            ->set('t.status', ':new')
            ->where('t.id = :id')
            ->andWhere('t.status = :expected')
            ->setParameter('new', $newStatus)
            ->setParameter('id', $id)
            ->setParameter('expected', $expectedStatus)
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    /**
     * Suma el monto de las transferencias ya completadas desde $account, con createdAt >= $since —
     * usado para validar dailyLimit/monthlyLimit contra dinero que realmente salió de la cuenta
     * (no contra transferencias pending que todavía podrían cancelarse).
     */
    public function sumCompletedAmountSince(Account $account, \DateTimeImmutable $since): string
    {
        $total = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.amount), 0)')
            ->andWhere('t.originAccount = :account')
            ->andWhere('t.status = :status')
            ->andWhere('t.createdAt >= :since')
            ->setParameter('account', $account)
            ->setParameter('status', 'completed')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) $total;
    }
}

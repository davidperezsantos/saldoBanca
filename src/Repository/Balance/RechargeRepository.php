<?php

namespace App\Repository\Balance;

use App\Entity\Balance\Recharge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recharge>
 */
class RechargeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recharge::class);
    }

    public function save(Recharge $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Recharge $entity, bool $flush = false): void
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
            ->update(Recharge::class, 'r')
            ->set('r.status', ':new')
            ->where('r.id = :id')
            ->andWhere('r.status = :expected')
            ->setParameter('new', $newStatus)
            ->setParameter('id', $id)
            ->setParameter('expected', $expectedStatus)
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    /**
     * Busca una recarga ya registrada para el mismo (pasarela, referencia) — soporte de
     * idempotencia de webhooks, respaldado además por el índice único parcial en BD.
     */
    public function findByExternalReference(string $externalSystem, string $referenceNumber): ?Recharge
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.externalSystem = :externalSystem')
            ->andWhere('r.referenceNumber = :referenceNumber')
            ->setParameter('externalSystem', $externalSystem)
            ->setParameter('referenceNumber', $referenceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recargas ya acreditadas (status=completed) para un sistema externo dentro de un período —
     * usado por la conciliación bancaria para detectar dinero que el sistema dio por bueno pero que
     * el externo no reporta ("missing_external").
     *
     * @return Recharge[]
     */
    public function findCompletedByExternalSystemSince(
        string $externalSystem,
        \DateTimeImmutable $since,
        ?\DateTimeImmutable $until = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.externalSystem = :externalSystem')
            ->andWhere('r.status = :status')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('externalSystem', $externalSystem)
            ->setParameter('status', 'completed')
            ->setParameter('since', $since);

        if ($until !== null) {
            $qb->andWhere('r.createdAt <= :until')
               ->setParameter('until', $until);
        }

        return $qb->getQuery()->getResult();
    }
}

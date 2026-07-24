<?php

namespace App\Services\Balance;

use App\Entity\Balance\BankReconciliation;
use App\Exception\ValidationException;
use App\Repository\Balance\BankReconciliationRepository;
use App\Repository\Balance\RechargeRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Compara lo que el sistema acreditó (Recharge.status=completed) para una pasarela/sistema externo
 * contra la lista de movimientos que ese externo reporta haber procesado (extracto bancario, reporte
 * de la pasarela, etc. — lo trae el llamador, este servicio no sabe hablar con bancos reales).
 *
 * Cuatro tipos de descuadre posibles:
 * - amount_mismatch: existe en ambos lados pero el monto no coincide.
 * - missing_internal: el externo reporta una transacción que el sistema no tiene registrada.
 * - not_completed_internal: el sistema la tiene pero nunca se acreditó (pending/failed/cancelled).
 * - missing_external: el sistema acreditó dinero que el externo no reporta (posible error/fraude).
 */
class ReconciliationService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private RechargeRepository $rechargeRepository,
        private BankReconciliationRepository $reconciliationRepository,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * @param array $externalTransactions Lista de ['referenceNumber' => string, 'amount' => string]
     *   reportados por el externo para $gatewayCode.
     */
    public function reconcile(
        string $gatewayCode,
        array $externalTransactions,
        ?\DateTimeImmutable $periodStart = null,
        ?\DateTimeImmutable $periodEnd = null,
        ?string $performedBy = null
    ): BankReconciliation {
        $since = $periodStart ?? new \DateTimeImmutable('-30 days');

        $discrepancies = [];
        $matched = 0;
        $mismatched = 0;
        $missingInternal = 0;
        $seenReferences = [];

        foreach ($externalTransactions as $external) {
            $referenceNumber = $external['referenceNumber'] ?? null;
            $amount = isset($external['amount']) ? (string) $external['amount'] : null;

            if (!$referenceNumber || $amount === null) {
                throw new ValidationException('Each external transaction requires referenceNumber and amount');
            }

            $seenReferences[$referenceNumber] = true;
            $recharge = $this->rechargeRepository->findByExternalReference($gatewayCode, $referenceNumber);

            if (!$recharge) {
                $missingInternal++;
                $discrepancies[] = [
                    'type' => 'missing_internal',
                    'referenceNumber' => $referenceNumber,
                    'externalAmount' => $amount,
                    'internalAmount' => null,
                    'internalStatus' => null,
                ];
                continue;
            }

            if ($recharge->getStatus() !== 'completed') {
                $discrepancies[] = [
                    'type' => 'not_completed_internal',
                    'referenceNumber' => $referenceNumber,
                    'externalAmount' => $amount,
                    'internalAmount' => $recharge->getAmount(),
                    'internalStatus' => $recharge->getStatus(),
                ];
                continue;
            }

            if (bccomp($recharge->getAmount(), $amount, 2) !== 0) {
                $mismatched++;
                $discrepancies[] = [
                    'type' => 'amount_mismatch',
                    'referenceNumber' => $referenceNumber,
                    'externalAmount' => $amount,
                    'internalAmount' => $recharge->getAmount(),
                    'internalStatus' => $recharge->getStatus(),
                ];
                continue;
            }

            $matched++;
        }

        $missingExternal = 0;
        $completedRecharges = $this->rechargeRepository->findCompletedByExternalSystemSince($gatewayCode, $since, $periodEnd);
        foreach ($completedRecharges as $recharge) {
            $referenceNumber = $recharge->getReferenceNumber();
            if ($referenceNumber !== null && isset($seenReferences[$referenceNumber])) {
                continue;
            }

            $missingExternal++;
            $discrepancies[] = [
                'type' => 'missing_external',
                'referenceNumber' => $referenceNumber,
                'externalAmount' => null,
                'internalAmount' => $recharge->getAmount(),
                'internalStatus' => $recharge->getStatus(),
            ];
        }

        $run = new BankReconciliation();
        $run->setGatewayCode($gatewayCode);
        $run->setPeriodStart($since);
        $run->setPeriodEnd($periodEnd);
        $run->setTotalExternal(count($externalTransactions));
        $run->setTotalMatched($matched);
        $run->setTotalMismatched($mismatched);
        $run->setTotalMissingInternal($missingInternal);
        $run->setTotalMissingExternal($missingExternal);
        $run->setDiscrepancies($discrepancies);
        $run->setPerformedBy($performedBy);

        $this->persist($run);
        $this->flush();

        return $run;
    }

    public function getRun(string $id): ?BankReconciliation
    {
        return $this->reconciliationRepository->find($id);
    }

    public function listRuns(array $filters = []): array
    {
        $qb = $this->reconciliationRepository->createQueryBuilder('br');

        if (isset($filters['gatewayCode'])) {
            $qb->andWhere('br.gatewayCode = :gatewayCode')
               ->setParameter('gatewayCode', $filters['gatewayCode']);
        }

        $qb->orderBy('br.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        return $qb->getQuery()->getResult();
    }
}

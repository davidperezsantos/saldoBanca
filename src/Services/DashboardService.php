<?php

namespace App\Services;

use App\Entity\Balance\Account;
use App\Entity\Balance\AccountBalance;
use App\Entity\Balance\AuthorizedUser;
use App\Entity\Balance\BusinessReconciliation;
use App\Entity\Balance\ExchangeRate;
use App\Entity\Balance\InvoicePayment;
use App\Entity\Balance\Recharge;
use App\Entity\Balance\Transfer;
use App\Repository\Balance\CurrencyRepository;
use App\Services\Balance\CommissionSettlementService;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    private const PERIODS = ['today', '7d', '30d'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurrencyRepository $currencyRepository,
        private SystemCurrencyService $systemCurrencyService,
        private CommissionSettlementService $commissionSettlementService,
    ) {
    }

    public function getStats(string $period = '7d'): array
    {
        if (!in_array($period, self::PERIODS, true)) {
            $period = '7d';
        }

        $since = $this->periodStart($period);

        return [
            'period' => $period,
            'balancesByCurrency' => $this->getBalancesByCurrency(),
            'accounts' => $this->getAccountStats(),
            'authorized' => $this->getAuthorizedStats(),
            'operations' => [
                'recharges' => $this->getOperationStats(Recharge::class, 'amount', $since),
                'transfers' => $this->getOperationStats(Transfer::class, 'amount', $since),
                'invoices' => $this->getOperationStats(InvoicePayment::class, 'totalAmount', $since),
            ],
            'recentRecharges' => $this->getRecentRecharges(),
            'recentTransfers' => $this->getRecentTransfers(),
            'recentInvoices' => $this->getRecentInvoices(),
            'exchangeRates' => $this->getExchangeRates(),
            'reconciliations' => $this->getReconciliationStats($since),
        ];
    }

    private function periodStart(string $period): \DateTimeImmutable
    {
        return match ($period) {
            'today' => new \DateTimeImmutable('today'),
            '30d' => new \DateTimeImmutable('-30 days'),
            default => new \DateTimeImmutable('-7 days'),
        };
    }

    /**
     * Saldo disponible y reservado del sistema, en la moneda base. Recharge/Transfer/Invoice
     * fuerzan la conversión a la moneda base antes de tocar el saldo (ver
     * RechargeService/TransferService/InvoiceService::createXxx()) — ninguna otra moneda mueve
     * plata nunca, aunque una cuenta tenga una fila de AccountBalance en otra moneda por su
     * defaultCurrency. Mostrar esas filas en el dashboard como si fueran a llenarse alguna vez es
     * engañoso (confirmado real: una recarga de $100 USD terminó en ~92 EUR, la fila de USD siguió
     * en 0 para siempre) — filtrar acá a la moneda base en vez de agrupar por todas.
     */
    private function getBalancesByCurrency(): array
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        return $this->entityManager->createQueryBuilder()
            ->select('ab.currency AS currency')
            ->addSelect('COALESCE(SUM(ab.availableBalance), 0) AS available')
            ->addSelect('COALESCE(SUM(ab.reservedBalance), 0) AS reserved')
            ->from(AccountBalance::class, 'ab')
            ->where('ab.currency = :baseCurrency')
            ->setParameter('baseCurrency', $baseCurrency)
            ->groupBy('ab.currency')
            ->getQuery()
            ->getResult();
    }

    private function getAccountStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $byStatus = $qb->select('a.status AS status', 'COUNT(a.id) AS total')
            ->from(Account::class, 'a')
            ->groupBy('a.status')
            ->getQuery()
            ->getResult();

        $qb = $this->entityManager->createQueryBuilder();
        $byType = $qb->select('a.accountType AS accounttype', 'COUNT(a.id) AS total')
            ->from(Account::class, 'a')
            ->groupBy('a.accountType')
            ->getQuery()
            ->getResult();

        $qb = $this->entityManager->createQueryBuilder();
        $total = (int) $qb->select('COUNT(a.id)')
            ->from(Account::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'byStatus' => array_map(fn(array $r) => ['status' => $r['status'], 'total' => (int) $r['total']], $byStatus),
            'byType' => array_map(fn(array $r) => ['accounttype' => $r['accounttype'], 'total' => (int) $r['total']], $byType),
        ];
    }

    private function getAuthorizedStats(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $total = (int) $qb->select('COUNT(au.id)')
            ->from(AuthorizedUser::class, 'au')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->entityManager->createQueryBuilder();
        $active = (int) $qb->select('COUNT(au.id)')
            ->from(AuthorizedUser::class, 'au')
            ->where('au.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'active' => $active,
        ];
    }

    /**
     * Cuenta y monto (agrupado por moneda) de una entidad de operaciones, desglosado por status,
     * limitado a la ventana de tiempo pedida.
     */
    private function getOperationStats(string $entityClass, string $amountField, \DateTimeImmutable $since): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('x.status AS status', 'x.currency AS currency')
            ->addSelect('COUNT(x.id) AS total')
            ->addSelect(sprintf('COALESCE(SUM(x.%s), 0) AS amount', $amountField))
            ->from($entityClass, 'x')
            ->where('x.createdAt >= :since')
            ->groupBy('x.status', 'x.currency')
            ->orderBy('x.status')
            ->addOrderBy('x.currency')
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $r) => [
            'status' => $r['status'],
            'currency' => $r['currency'],
            'total' => (int) $r['total'],
            'amount' => $r['amount'],
        ], $rows);
    }

    private function getRecentRecharges(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r.id AS id, r.amount AS amount, r.currency AS currency, r.status AS status, r.createdAt AS createdat')
            ->addSelect('a.accountNumber AS accountnumber, a.businessName AS businessname')
            ->from(Recharge::class, 'r')
            ->join('r.account', 'a')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $r) => $this->normalizeRow($r), $rows);
    }

    private function getRecentTransfers(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.id AS id, t.amount AS amount, t.currency AS currency, t.status AS status, t.createdAt AS createdat')
            ->addSelect('fa.accountNumber AS from_account, ta.accountNumber AS to_account')
            ->from(Transfer::class, 't')
            ->join('t.originAccount', 'fa')
            ->join('t.destinationAccount', 'ta')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $r) => $this->normalizeRow($r), $rows);
    }

    private function getRecentInvoices(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('i.id AS id, i.invoiceNumber AS invoicenumber, i.totalAmount AS totalamount')
            ->addSelect('i.currency AS currency, i.status AS status, i.createdAt AS createdat')
            ->addSelect('a.accountNumber AS accountnumber, a.businessName AS businessname')
            ->from(InvoicePayment::class, 'i')
            ->join('i.account', 'a')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return array_map(fn(array $r) => $this->normalizeRow($r), $rows);
    }

    /**
     * Tasas vigentes (una fila por moneda activa distinta de la base) — filtra por
     * fromCurrency = moneda base del sistema, ya que esa es la única dirección que el resto de la
     * app usa para convertir (APIExchangeRate::getRate()); las monedas deshabilitadas del
     * nomenclador ni siquiera entran en la lista. leftJoin (no join) porque las tasas manuales
     * (ej. CURRENCY_SECUNDARY) no tienen proveedor asociado y antes quedaban excluidas.
     */
    private function getExchangeRates(): array
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $activeCodes = array_values(array_filter(
            $this->currencyRepository->findActiveCodes(),
            fn(string $code) => $code !== $baseCurrency
        ));
        if (empty($activeCodes)) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('er.fromCurrency AS fromcurrency, er.toCurrency AS tocurrency, er.rate AS rate, er.fetchedAt AS fetchedat')
            ->addSelect('ep.name AS provider_name')
            ->from(ExchangeRate::class, 'er')
            ->leftJoin('er.provider', 'ep')
            ->where('er.isActive = true')
            ->andWhere('er.fromCurrency = :baseCurrency')
            ->andWhere('er.toCurrency IN (:activeCodes)')
            ->setParameter('baseCurrency', $baseCurrency)
            ->setParameter('activeCodes', $activeCodes)
            ->orderBy('er.toCurrency', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn(array $r) => $this->normalizeRow($r), $rows);
    }

    /**
     * Cuánto se liquidó/pagó a negocios en cada moneda del split (ver BusinessReconciliation),
     * dentro de la ventana de tiempo pedida — mismo $since que el resto de las estadísticas del
     * dashboard, medido por settledAt. La comisión, en cambio, se muestra como "disponible" (ver
     * CommissionSettlementService::getAvailableCommission()) y NO se acota por período — es un
     * saldo corriente, no algo que "pase" dentro de un rango de fechas: baja apenas se crea una
     * CommissionSettlement contra ella (se reserva desde ese momento, no recién al cerrarse), igual
     * que en el formulario de alta de una liquidación.
     */
    private function getReconciliationStats(\DateTimeImmutable $since): array
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(r.settlementBaseAmount), 0) AS baseamount')
            ->addSelect('COALESCE(SUM(r.settlementSecondaryAmount), 0) AS secondaryamount')
            ->from(BusinessReconciliation::class, 'r')
            ->where('r.status = :status')
            ->andWhere('r.settledAt >= :since')
            ->setParameter('status', 'settled')
            ->setParameter('since', $since)
            ->getQuery()
            ->getOneOrNullResult();

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        return [
            'baseCurrency' => $baseCurrency,
            'baseAmount' => $row['baseamount'] ?? '0.00',
            'taxAmount' => $this->commissionSettlementService->getAvailableCommission($baseCurrency),
            'secondaryCurrency' => $this->systemCurrencyService->getSecondaryCurrency(),
            'secondaryAmount' => $row['secondaryamount'] ?? '0.00',
        ];
    }

    /**
     * Las filas DQL con campos sueltos (no hidratación de entidad) devuelven objetos Uuid/DateTime
     * tal cual; los normaliza a string para que sean serializables en window.__STATS__.
     */
    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value)) {
                $row[$key] = (string) $value;
            }
        }

        return $row;
    }
}

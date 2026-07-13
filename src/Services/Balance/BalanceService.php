<?php

namespace App\Services\Balance;

use App\Entity\Balance\AccountBalance;
use App\Entity\Balance\BalanceMovement;
use App\Repository\Balance\AccountBalanceRepository;
use App\Repository\Balance\AccountRepository;
use App\Repository\Balance\BalanceMovementRepository;
use App\Services\BaseService;
use Doctrine\ORM\EntityManagerInterface;

class BalanceService extends BaseService
{
    private AccountRepository $accountRepository;
    private AccountBalanceRepository $balanceRepository;
    private BalanceMovementRepository $movementRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        AccountBalanceRepository $balanceRepository,
        BalanceMovementRepository $movementRepository
    ) {
        parent::__construct($entityManager);
        $this->accountRepository = $accountRepository;
        $this->balanceRepository = $balanceRepository;
        $this->movementRepository = $movementRepository;
    }

    public function getBalance(string $accountId, string $currency = 'USD'): ?AccountBalance
    {
        return $this->balanceRepository->findByAccountAndCurrency($accountId, $currency);
    }

    public function addBalance(
        string $accountId,
        string $amount,
        string $currency,
        string $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $performedBy = null
    ): AccountBalance {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $balance = $this->balanceRepository->findByAccountAndCurrency($accountId, $currency);
        if (!$balance) {
            $balance = new AccountBalance();
            $balance->setAccount($account);
            $balance->setCurrency($currency);
            $balance->setAvailableBalance('0.00');
            $balance->setPendingBalance('0.00');
            $balance->setReservedBalance('0.00');
            $balance->setTotalRecharged('0.00');
            $balance->setTotalTransferred('0.00');
            $balance->setTotalInvoiced('0.00');
            $this->persist($balance);
        }

        $balanceBefore = $balance->getAvailableBalance();
        $balanceAfter = bcadd($balanceBefore, $amount, 2);

        $balance->setAvailableBalance($balanceAfter);
        $balance->setLastMovementAt(new \DateTimeImmutable());

        if ($type === 'recharge') {
            $balance->setTotalRecharged(bcadd($balance->getTotalRecharged(), $amount, 2));
        }

        $movement = new BalanceMovement();
        $movement->setAccount($account);
        $movement->setMovementType($type);
        $movement->setAmount($amount);
        $movement->setBalanceBefore($balanceBefore);
        $movement->setBalanceAfter($balanceAfter);
        $movement->setReferenceType($referenceType);
        $movement->setReferenceId($referenceId);
        $movement->setDescription($description);
        $movement->setCurrency($currency);
        $movement->setPerformedBy($performedBy);

        $this->persist($movement);
        $this->flush();

        return $balance;
    }

    public function deductBalance(
        string $accountId,
        string $amount,
        string $currency,
        string $type,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $performedBy = null
    ): AccountBalance {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $balance = $this->balanceRepository->findByAccountAndCurrency($accountId, $currency);
        if (!$balance) {
            throw new \RuntimeException('Balance not found for currency: ' . $currency);
        }

        if (bccomp($balance->getAvailableBalance(), $amount, 2) < 0) {
            throw new \RuntimeException('Insufficient balance');
        }

        $balanceBefore = $balance->getAvailableBalance();
        $balanceAfter = bcsub($balanceBefore, $amount, 2);

        $balance->setAvailableBalance($balanceAfter);
        $balance->setLastMovementAt(new \DateTimeImmutable());

        if ($type === 'transfer_out') {
            $balance->setTotalTransferred(bcadd($balance->getTotalTransferred(), $amount, 2));
        } elseif ($type === 'invoice_pay') {
            $balance->setTotalInvoiced(bcadd($balance->getTotalInvoiced(), $amount, 2));
        }

        $movement = new BalanceMovement();
        $movement->setAccount($account);
        $movement->setMovementType($type);
        $movement->setAmount('-' . $amount);
        $movement->setBalanceBefore($balanceBefore);
        $movement->setBalanceAfter($balanceAfter);
        $movement->setReferenceType($referenceType);
        $movement->setReferenceId($referenceId);
        $movement->setDescription($description);
        $movement->setCurrency($currency);
        $movement->setPerformedBy($performedBy);

        $this->persist($movement);
        $this->flush();

        return $balance;
    }

    public function transferBalance(
        string $originAccountId,
        string $destAccountId,
        string $amount,
        string $currency,
        ?string $performedBy = null
    ): array {
        $originBalance = $this->deductBalance(
            $originAccountId,
            $amount,
            $currency,
            'transfer_out',
            'account',
            $destAccountId,
            'Transfer to account ' . $destAccountId,
            $performedBy
        );

        $destBalance = $this->addBalance(
            $destAccountId,
            $amount,
            $currency,
            'transfer_in',
            'account',
            $originAccountId,
            'Transfer from account ' . $originAccountId,
            $performedBy
        );

        return [
            'origin' => $originBalance,
            'destination' => $destBalance,
        ];
    }

    public function getMovements(string $accountId, array $filters = []): array
    {
        $qb = $this->movementRepository->createQueryBuilder('bm')
            ->andWhere('bm.account = :accountId')
            ->setParameter('accountId', $accountId);

        if (isset($filters['movementType'])) {
            $qb->andWhere('bm.movementType = :movementType')
               ->setParameter('movementType', $filters['movementType']);
        }

        if (isset($filters['currency'])) {
            $qb->andWhere('bm.currency = :currency')
               ->setParameter('currency', $filters['currency']);
        }

        if (isset($filters['dateFrom'])) {
            $qb->andWhere('bm.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $filters['dateFrom']);
        }

        if (isset($filters['dateTo'])) {
            $qb->andWhere('bm.createdAt <= :dateTo')
               ->setParameter('dateTo', $filters['dateTo']);
        }

        $qb->orderBy('bm.createdAt', 'DESC');

        if (isset($filters['limit'])) {
            $qb->setMaxResults($filters['limit']);
        }

        if (isset($filters['offset'])) {
            $qb->setFirstResult($filters['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    public function getMovementSummary(string $accountId, string $period = 'month'): array
    {
        $balance = $this->balanceRepository->findByAccountAndCurrency($accountId, 'USD');

        $movements = $this->getMovements($accountId, ['limit' => 100]);

        $summary = [
            'total_recharged' => '0.00',
            'total_transferred_out' => '0.00',
            'total_transferred_in' => '0.00',
            'total_invoiced' => '0.00',
            'movements_count' => count($movements),
        ];

        foreach ($movements as $movement) {
            $amount = $movement->getAmount();
            switch ($movement->getMovementType()) {
                case 'recharge':
                    $summary['total_recharged'] = bcadd($summary['total_recharged'], $amount, 2);
                    break;
                case 'transfer_out':
                    $summary['total_transferred_out'] = bcadd($summary['total_transferred_out'], $amount, 2);
                    break;
                case 'transfer_in':
                    $summary['total_transferred_in'] = bcadd($summary['total_transferred_in'], $amount, 2);
                    break;
                case 'invoice_pay':
                    $summary['total_invoiced'] = bcadd($summary['total_invoiced'], $amount, 2);
                    break;
            }
        }

        return $summary;
    }

    public function recalculateBalance(string $accountId): AccountBalance
    {
        $movements = $this->getMovements($accountId, ['limit' => 10000]);

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new \RuntimeException('Account not found');
        }

        $balance = $this->balanceRepository->findByAccountAndCurrency($accountId, $account->getDefaultCurrency());
        if (!$balance) {
            throw new \RuntimeException('Balance not found');
        }

        $totalRecharged = '0.00';
        $totalTransferred = '0.00';
        $totalInvoiced = '0.00';

        foreach ($movements as $movement) {
            $amount = $movement->getAmount();
            switch ($movement->getMovementType()) {
                case 'recharge':
                    $totalRecharged = bcadd($totalRecharged, $amount, 2);
                    break;
                case 'transfer_out':
                    $totalTransferred = bcadd($totalTransferred, $amount, 2);
                    break;
                case 'invoice_pay':
                    $totalInvoiced = bcadd($totalInvoiced, $amount, 2);
                    break;
            }
        }

        $availableBalance = bcsub(bcadd($totalRecharged, $totalTransferred, 2), $totalInvoiced, 2);

        $balance->setAvailableBalance($availableBalance);
        $balance->setTotalRecharged($totalRecharged);
        $balance->setTotalTransferred($totalTransferred);
        $balance->setTotalInvoiced($totalInvoiced);

        $this->flush();

        return $balance;
    }
}

<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getStats(): array
    {
        $conn = $this->entityManager->getConnection();

        $totalAccounts = (int) $conn->fetchOne('SELECT COUNT(*) FROM balance_account');
        $activeAccounts = (int) $conn->fetchOne("SELECT COUNT(*) FROM balance_account WHERE status = 'active'");
        $totalRecharges = (float) ($conn->fetchOne("SELECT COALESCE(SUM(amount), 0) FROM balance_recharge WHERE status = 'completed'") ?: 0);
        $totalTransfers = (float) ($conn->fetchOne("SELECT COALESCE(SUM(amount), 0) FROM balance_transfer WHERE status = 'completed'") ?: 0);
        $pendingInvoices = (int) $conn->fetchOne("SELECT COUNT(*) FROM balance_invoice_payment WHERE status = 'pending'");
        $totalBalance = (float) ($conn->fetchOne('SELECT COALESCE(SUM(availablebalance), 0) FROM balance_account_balance') ?: 0);

        $recentRecharges = $conn->fetchAllAssociative("
            SELECT r.id, r.amount, r.status, r.createdat, a.accountnumber, a.businessname
            FROM balance_recharge r
            JOIN balance_account a ON r.account_id = a.id
            ORDER BY r.createdat DESC
            LIMIT 5
        ");

        $recentTransfers = $conn->fetchAllAssociative("
            SELECT t.id, t.amount, t.status, t.createdat,
                   fa.accountnumber as from_account, ta.accountnumber as to_account
            FROM balance_transfer t
            JOIN balance_account fa ON t.originaccount_id = fa.id
            JOIN balance_account ta ON t.destinationaccount_id = ta.id
            ORDER BY t.createdat DESC
            LIMIT 5
        ");

        return [
            'totalAccounts' => $totalAccounts,
            'activeAccounts' => $activeAccounts,
            'totalRecharges' => $totalRecharges,
            'totalTransfers' => $totalTransfers,
            'pendingInvoices' => $pendingInvoices,
            'totalBalance' => $totalBalance,
            'recentRecharges' => $recentRecharges,
            'recentTransfers' => $recentTransfers,
        ];
    }
}

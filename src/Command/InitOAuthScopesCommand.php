<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:oauth:init-scopes',
    description: 'Show configured OAuth2 scopes'
)]
class InitOAuthScopesCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $scopes = [
            'accounts.read' => 'List/view accounts',
            'accounts.create' => 'Create accounts',
            'accounts.update' => 'Update account data',
            'accounts.status' => 'Activate/suspend/close accounts',
            'accounts.request_pin' => 'Request a new verification PIN be sent to an account',
            'payout_accounts.read' => 'List/view a business\'s payout accounts',
            'payout_accounts.create' => 'Register a payout account for a business',
            'payout_accounts.update' => 'Update a business payout account',
            'payout_accounts.delete' => 'Delete a business payout account',
            'authorized.read' => 'List/view/verify authorized users',
            'authorized.create' => 'Create authorized users',
            'authorized.update' => 'Update authorized users',
            'authorized.delete' => 'Delete authorized users',
            'authorized.status' => 'Activate/deactivate authorized users',
            'authorized.charge' => 'Charge (spend) against an authorized user\'s balance',
            'authorized.reset_password' => 'Trigger a password reset for an authorized user',
            'authorized.request_pin' => 'Request a new verification PIN be sent to an authorized user',
            'balance.read' => 'View account balance',
            'recharges.read' => 'List/view recharges',
            'recharges.create' => 'Create recharges',
            'recharges.complete' => 'Complete (credit) a pending recharge',
            'recharges.cancel' => 'Cancel a pending recharge',
            'recharges.fail' => 'Mark a recharge as failed',
            'transfers.read' => 'List/view transfers and limits',
            'transfers.create' => 'Create transfers',
            'transfers.process' => 'Process a pending transfer',
            'transfers.cancel' => 'Cancel a pending transfer',
            'invoices.read' => 'List/view invoices and summaries',
            'invoices.create' => 'Create invoices',
            'invoices.pay' => 'Pay a pending invoice',
            'invoices.cancel' => 'Cancel an invoice',
            'invoices.refund' => 'Refund a paid invoice',
            'history.read' => 'View balance movement history',
            'exchange_rates.read' => 'View exchange rates',
            'exchange_providers.read' => 'View exchange rate providers',
            'payment_gateways.read' => 'View payment gateways',
            'commission_settlements.read' => 'List/view system commission settlements',
            'commission_settlements.create' => 'Create a commission settlement',
            'commission_settlements.request_pin' => 'Request/verify a PIN for a commission settlement action',
            'commission_settlements.approve' => 'Approve a commission settlement (admin)',
            'commission_settlements.assign_account' => 'Assign the payout account for a commission settlement (super admin)',
            'commission_settlements.settle' => 'Mark a commission settlement as settled (admin)',
            'commission_settlements.close' => 'Close a commission settlement (super admin)',
        ];

        $io->title('OAuth2 Scopes Configuration');
        $io->table(['Scope', 'Description'], array_map(
            fn($id, $desc) => [$id, $desc],
            array_keys($scopes),
            array_values($scopes)
        ));

        $io->note('Scopes are configured in config/packages/league_oauth2_server.yaml under "scopes.available".');

        $io->success('OAuth2 scopes are configured via YAML bundle configuration');

        return Command::SUCCESS;
    }
}

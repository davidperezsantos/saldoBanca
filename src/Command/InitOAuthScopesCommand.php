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
            'email' => 'Access to user email',
            'profile' => 'Access to user profile',
            'accounts' => 'Access to accounts',
            'recharges' => 'Access to recharges',
            'transfers' => 'Access to transfers',
            'invoices' => 'Access to invoices',
            'authorized' => 'Access to authorized users',
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

<?php

namespace App\Command;

use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:oauth:create-client',
    description: 'Create an OAuth2 client'
)]
class CreateOAuthClientCommand extends Command
{
    public function __construct(
        private ClientManagerInterface $clientManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $io->ask('Client name', 'Saldo Banca API');
        $identifier = $io->ask('Client identifier', 'saldo_banca_api');
        $secret = $io->ask('Client secret', bin2hex(random_bytes(32)));
        $redirectUris = $io->ask('Redirect URIs (comma separated)', 'http://localhost:8000');

        $client = new Client($name, $identifier, $secret);
        $client->setActive(true);

        $uris = array_map(fn($uri) => new RedirectUri(trim($uri)), explode(',', $redirectUris));
        $client->setRedirectUris(...$uris);

        $client->setGrants(
            new Grant('authorization_code'),
            new Grant('refresh_token'),
            new Grant('client_credentials'),
        );

        $client->setScopes(
            new Scope('email'),
            new Scope('profile'),
            new Scope('accounts'),
            new Scope('recharges'),
            new Scope('transfers'),
            new Scope('invoices'),
            new Scope('authorized'),
        );

        $this->clientManager->save($client);

        $io->success('OAuth2 client created successfully');
        $io->note('Client ID: ' . $identifier);
        $io->note('Client Secret: ' . $secret);

        return Command::SUCCESS;
    }
}

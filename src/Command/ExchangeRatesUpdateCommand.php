<?php

namespace App\Command;

use App\Services\ExchangeRate\APIExchangeRate;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:exchange-rates:update',
    description: 'Add a short description for your command',
)]
class ExchangeRatesUpdateCommand extends Command
{
    private APIExchangeRate $exchangeRate;

    public function __construct( APIExchangeRate $exchangeRate)
    {
        $this->exchangeRate = $exchangeRate;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔄 Actualizando exchange rates...');

        //$data = $this->apiFixer->refreshRates();

        $data = $this->exchangeRate->refreshRates();

        if (isset($data['error'])) {
            $output->writeln('<error>Error: '.$data['error'].'</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>✔ Rates actualizados correctamente</info>');
        $output->writeln('Rates: ' . json_encode($data['rates']));
        $output->writeln('Base: '.$data['base']);

        return Command::SUCCESS;
    }
}

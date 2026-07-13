<?php

namespace App\Services\ExchangeRate;

use App\Entity\Main\ExchangeRates\Fixer;
use App\Repository\Main\ExchangeRates\FixerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Psr\Log\LoggerInterface;

class APIFixer
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private FixerRepository $fixerRepository,
        private string $url,
        private string $apiKey,
        private string $simbols,
        private string $base,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Obtiene los exchange rates desde cache o API
     */
    public function getExchangeRates(): array
    {        
        $lastFixer = $this->fixerRepository->findLast();

        // SI EXISTE Y NO HA EXPIRADO (1 hora)
        if ($lastFixer) {

            $diff = time() - $lastFixer->getCreatedAt()->getTimestamp();

            if ($diff < 3600) {

                return [
                    'success' => true,
                    'timestamp' => $lastFixer->getTimestampApi(),
                    'base' => $lastFixer->getBase(),
                    'date' => $lastFixer->getDateApi(),
                    'rates' => $lastFixer->getRates()
                ];
            }
        }

        // CONSULTAR API
        return $this->fetchAndStore();
    }

    /**
     * Consultar API y guardar en BD
     */
    private function fetchAndStore(): array
    {
        $url = sprintf(
            '%s?access_key=%s&symbols=%s&base=%s',
            $this->url,
            $this->apiKey,
            $this->simbols,
            $this->base
        );

        try {

            $this->logger->info('Consultando API Fixer', [
                'url' => $url
            ]);

            $response = $this->httpClient->request('GET', $url);

            $data = $response->toArray();

            $fixer = new Fixer();
            $fixer->setBase($data['base']);
            $fixer->setRates($data['rates'] ?? []);
            $fixer->setTimestampApi($data['timestamp']);
            $fixer->setDateApi($data['date']);

            $this->em->persist($fixer);
            $this->em->flush();

            return [
                'success' => true,
                'timestamp' => $fixer->getTimestampApi(),
                'base' => $fixer->getBase(),
                'date' => $fixer->getDateApi(),
                'rates' => $fixer->getRates()
            ];

        } catch (\Throwable $e) {

            $this->logger->error('Error API Fixer', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rates' => []
            ];
        }
    }    

    /**
     * Conversión
     */
    public function convert(float $amount, string $currency): float
    {
        $data = $this->getExchangeRates();

        if (!isset($data['rates'][$currency])) {
            throw new \Exception("Currency {$currency} not supported");
        }

        return round(
            $amount * (float)$data['rates'][$currency],
            2
        );
    }

    /**
     * Obtener rate específico
     */
    public function getRate(string $currency): ?float
    {
        $data = $this->getExchangeRates();

        return $data['rates'][$currency] ?? null;
    }

    /**
     * Refresh manual
     */
    public function refreshRates(): array
    {
        return $this->fetchAndStore();
    }
}
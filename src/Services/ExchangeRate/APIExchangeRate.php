<?php

namespace App\Services\ExchangeRate;

use App\Entity\Balance\ExchangeRateProvider;
use App\Entity\Balance\ExchangeRateSnapshot;
use App\Repository\Balance\ExchangeRateSnapshotRepository;
use App\Repository\Balance\ExchangeRateProviderRepository;
use App\Services\SystemCurrencyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class APIExchangeRate
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private ExchangeRateProviderRepository $providerRepository,
        private ExchangeRateSnapshotRepository $snapshotRepository,
        private LoggerInterface $logger,
        private SystemCurrencyService $systemCurrencyService,
    ) {
    }

    private function getActiveProvider(): ?ExchangeRateProvider
    {
        return $this->providerRepository->findOneActive();
    }

    /**
     * Obtiene los exchange rates desde cache o API del proveedor activo
     */
    public function getExchangeRates(): array
    {
        $provider = $this->getActiveProvider();
        if (!$provider) {
            return ['success' => false, 'error' => 'No hay proveedor de tasa activo', 'rates' => []];
        }

        $last = $this->snapshotRepository->findLast();

        if ($last) {
            $diff = time() - $last->getCreatedAt()->getTimestamp();
            if ($diff < 3600) {
                return [
                    'success' => true,
                    'timestamp' => $last->getTimestampApi(),
                    'base' => $last->getBase(),
                    'rates' => $last->getRates(),
                ];
            }
        }

        return $this->fetchAndStore($provider);
    }

    private function fetchAndStore(ExchangeRateProvider $provider): array
    {
        $url = $provider->getApiUrl();
        if (!$url) {
            return ['success' => false, 'error' => 'URL del proveedor no configurada', 'rates' => []];
        }

         $url = sprintf(
            '%s%s/latest/%s',
            $url,
            $provider->getToken(),
            $this->systemCurrencyService->getBaseCurrency()
        );

        try {

            $response = $this->httpClient->request('GET', $url);
            $data = $response->toArray();

            $snapshot = new ExchangeRateSnapshot();
            $snapshot->setBase($data['base_code'] ?? 'EUR');
            $snapshot->setRates($data['conversion_rates'] ?? []);
            $snapshot->setTimestampApi((string)($data['time_last_update_unix'] ?? time()));

            $this->em->persist($snapshot);
            $this->em->flush();

            return [
                'success' => true,
                'timestamp' => $snapshot->getTimestampApi(),
                'base' => $snapshot->getBase(),
                'rates' => $snapshot->getRates(),
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Error al consultar API de tasas', [
                'provider' => $provider->getName(),
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rates' => [],
            ];
        }
    }

    /**
     * Convierte un monto de la moneda base del sistema a la moneda destino
     */
    public function convert(float $amount, string $currency): float
    {
        $data = $this->getExchangeRates();

        if (!$data['success']) {
            throw new \Exception($data['error'] ?? 'Error al obtener tasas de cambio');
        }

        if (!isset($data['rates'][$currency])) {
            throw new \Exception("Moneda {$currency} no soportada");
        }

        return round(
            $amount * (float)$data['rates'][$currency],
            2
        );
    }

    /**
     * Obtiene la tasa de una moneda específica (cuánto vale 1 moneda base en la moneda destino)
     */
    public function getRate(string $currency): ?float
    {
        $data = $this->getExchangeRates();

        if (!$data['success']) {
            return null;
        }

        return isset($data['rates'][$currency]) ? (float)$data['rates'][$currency] : null;
    }

    /**
     * Refresca las tasas manualmente
     */
    public function refreshRates(): array
    {
        $provider = $this->getActiveProvider();
        if (!$provider) {
            return ['success' => false, 'error' => 'No hay proveedor de tasa activo'];
        }
        return $this->fetchAndStore($provider);
    }
}

<?php

namespace App\Services\ExchangeRate;

use App\Entity\Balance\ExchangeRateProvider;
use App\Entity\Balance\ExchangeRate;
use App\Entity\Balance\ExchangeRateSnapshot;
use App\Exception\BusinessException;
use App\Repository\Balance\CurrencyRepository;
use App\Repository\Balance\ExchangeRateRepository;
use App\Repository\Balance\ExchangeRateSnapshotRepository;
use App\Repository\Balance\ExchangeRateProviderRepository;
use App\Services\SystemCurrencyService;
use App\Util\BcMath;
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
        private ExchangeRateRepository $exchangeRateRepository,
        private CurrencyRepository $currencyRepository,
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

            $baseCurrency = $snapshot->getBase();
            $now = new \DateTimeImmutable();
            $rates = $snapshot->getRates();
            // Solo se guardan las monedas habilitadas en el nomenclador al momento de este fetch
            // (App\Entity\Balance\Currency) — el resto de las ~165 que devuelve la API no aportan
            // nada al sistema. Cada llamada agrega filas nuevas (no se borran las anteriores) para
            // que esta tabla funcione como historial a través de múltiples fetches (manuales o, en
            // el futuro, un cron).
            $activeCodes = $this->currencyRepository->findActiveCodes();

            foreach ($rates as $currency => $rate) {
                if ($currency === $baseCurrency) continue;
                if (!in_array($currency, $activeCodes, true)) continue;
                if ((float)$rate <= 0) continue;

                // Solo puede haber una fila activa por moneda destino a la vez — es la que usa
                // getRate() para las conversiones. Se buscan TODAS las activas para esa moneda sin
                // importar el origen (no solo la del par exacto con la base actual), porque si el
                // origen cambió alguna vez (ej. CURRENCY cambió de moneda base) quedarían filas
                // huérfanas que ya nadie vuelve a desactivar.
                $previousRows = $this->exchangeRateRepository->findActiveByToCurrency($currency);

                // Una tasa manual bloqueada no se toca: se descarta el valor que trajo la API para
                // esta moneda en este ciclo y se sigue con la próxima.
                $hasLocked = false;
                foreach ($previousRows as $previous) {
                    if ($previous->isLocked()) {
                        $hasLocked = true;
                        break;
                    }
                }
                if ($hasLocked) {
                    continue;
                }

                foreach ($previousRows as $previous) {
                    $previous->setIsActive(false);
                }

                $rateStr = sprintf('%.4f', (float)$rate);

                $er = new ExchangeRate();
                $er->setProvider($provider);
                $er->setFromCurrency($baseCurrency);
                $er->setToCurrency($currency);
                $er->setRate($rateStr);
                $er->setInverseRate(BcMath::round(bcdiv('1', $rateStr, 10), 4));
                $er->setFetchedAt($now);
                $er->setIsActive(true);
                $this->em->persist($er);
            }

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
     * Obtiene la tasa de una moneda específica (cuánto vale 1 moneda base en la moneda destino),
     * leyendo la fila activa (isActive=true) para ese par en ExchangeRate — hay como máximo una
     * por par, mantenida por fetchAndStore(). Si no hay ninguna todavía (primera vez, o la moneda
     * se activó después del último fetch), se dispara un fetch en vivo una única vez y se reintenta.
     */
    public function getRate(string $currency): ?float
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        $rate = $this->exchangeRateRepository->findLatestByPair($baseCurrency, $currency);
        if ($rate) {
            return (float) $rate->getRate();
        }

        $refreshed = $this->refreshRates();
        if (!$refreshed['success']) {
            return null;
        }

        $rate = $this->exchangeRateRepository->findLatestByPair($baseCurrency, $currency);
        return $rate ? (float) $rate->getRate() : null;
    }

    /**
     * Convierte un monto de una moneda origen a la moneda base del sistema, usando la tasa
     * vigente (refrescándola si hace falta). Lógica compartida entre el convertidor de la API
     * pública (Api\ExchangeRateController) y el del panel Admin (Admin\RechargeController) —
     * antes vivía duplicada solo en el controlador de la API, y el panel Admin llamaba
     * directamente a ese endpoint público en vez de tener el suyo propio.
     *
     * @return array{originalAmount: string, originalCurrency: string, convertedAmount: string, baseCurrency: string, rate: string}
     */
    public function convertToBase(string $amount, string $currency): array
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        if ($currency === $baseCurrency) {
            return [
                'originalAmount' => $amount,
                'originalCurrency' => $currency,
                'convertedAmount' => $amount,
                'baseCurrency' => $baseCurrency,
                'rate' => '1.00',
            ];
        }

        // getRate() ya intenta un fetch en vivo si no hay tasa activa para $currency.
        $rate = $this->getRate($currency);
        if ($rate === null) {
            throw new BusinessException("No se encontró tasa para {$currency} ni después de consultar el proveedor");
        }

        $convertedAmount = (string) round((float) bcdiv($amount, sprintf('%.8f', $rate), 4), 2);

        return [
            'originalAmount' => $amount,
            'originalCurrency' => $currency,
            'convertedAmount' => $convertedAmount,
            'baseCurrency' => $baseCurrency,
            'rate' => (string) $rate,
        ];
    }

    /**
     * Convierte un monto entre dos monedas cualquiera, pasando por la base del sistema —
     * compartido entre BusinessReconciliationService::convertAmount() (reporte de conciliaciones)
     * y CommissionSettlementService::getAvailableCommission() (comisión disponible en la moneda
     * elegida al crear una liquidación). Devuelve null si no hay tasa disponible para alguna moneda.
     */
    public function convertBetween(?string $amount, string $fromCurrency, string $toCurrency): ?string
    {
        if ($amount === null) {
            return null;
        }
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $amountInBase = $this->convertToBase($amount, $fromCurrency)['convertedAmount'];

        if ($toCurrency === $this->systemCurrencyService->getBaseCurrency()) {
            return $amountInBase;
        }

        $rate = $this->getRate($toCurrency);
        return $rate !== null ? (string) round((float) bcmul($amountInBase, (string) $rate, 4), 2) : null;
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

    /**
     * Fija manualmente la tasa para una moneda, reemplazando la que estuviera activa (sin
     * proveedor asociado — no viene de una API). Si $locked es true, fetchAndStore() dejará de
     * sobrescribirla en los próximos fetches automáticos hasta que se registre otra tasa manual
     * para esa misma moneda.
     */
    public function createManualRate(string $toCurrency, string $rate, bool $locked): ExchangeRate
    {
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        if ($toCurrency === $baseCurrency) {
            throw new BusinessException("No se puede fijar una tasa manual para la moneda base ({$baseCurrency})");
        }
        if (!in_array($toCurrency, $this->currencyRepository->findActiveCodes(), true)) {
            throw new BusinessException("{$toCurrency} no es una moneda habilitada en el nomenclador");
        }
        if ((float) $rate <= 0) {
            throw new BusinessException('La tasa debe ser mayor que cero');
        }

        // Igual que en fetchAndStore(): desactiva cualquier fila activa para esta moneda destino,
        // sin importar el origen, para no dejar huérfanas las de un origen viejo.
        foreach ($this->exchangeRateRepository->findActiveByToCurrency($toCurrency) as $previous) {
            $previous->setIsActive(false);
        }

        $rateStr = sprintf('%.4f', (float) $rate);

        $er = new ExchangeRate();
        $er->setProvider(null);
        $er->setFromCurrency($baseCurrency);
        $er->setToCurrency($toCurrency);
        $er->setRate($rateStr);
        $er->setInverseRate(BcMath::round(bcdiv('1', $rateStr, 10), 4));
        $er->setFetchedAt(new \DateTimeImmutable());
        $er->setIsActive(true);
        $er->setIsLocked($locked);
        $this->em->persist($er);
        $this->em->flush();

        return $er;
    }
}

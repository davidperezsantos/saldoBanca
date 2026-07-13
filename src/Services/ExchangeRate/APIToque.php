<?php

namespace App\Services\ExchangeRate;

use App\Entity\Administrator\ExchangeRates\Toque;
use App\Entity\Administrator\Nomenclator\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class APIToque
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private string $url;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $url, string $apiKey, EntityManagerInterface $entityManager)
    {
        $this->httpClient = $httpClient;
        $this->url = $url;
        $this->apiKey = $apiKey;
        $this->entityManager = $entityManager;
    }

    protected function getExchangeRates(): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return ['error' => 'Error al obtener los datos: ' . $e->getMessage()];
        }
    }

    /**
     * Obtiene la última tasa desde la BD.
     */
    protected function getLastFromDB(array $search = [])
    {
        $lastToque = $this->entityManager
            ->getRepository(Toque::class)
            ->findOneBy($search, ['createdAt' => 'DESC']); // suponiendo que tienes un campo createdAt

        return $lastToque;
    }


    public function getCalculateValue(float $amount, string $from = 'ECU'): array
    {
        $amounEnd = [];
        $rates = $this->getLastFromDB();
        if (!$rates) {
            return ['error' => 'No hay tasas en la base de datos'];
        }
        $rates = $rates->getToque();
        // APLICO EL CALCULO PARA CUP
        $cupAmount = $amount * $rates[$from];
        $amounEnd['CUP'] = $cupAmount;
        // FIN APLICO EL CALCULO PARA CUP

        foreach ($rates as $key => $rate) {
            $calculate = ($amount * $rates[$from]) / $rates[$key];
            $amounEnd[$key] = $calculate;
        }

        return $amounEnd;

    }

    public function getOneTasasActual(string $from = 'EUR'): string
    {
        if ($from == 'EUR') {
            $from = 'ECU';
        }

        $rates = $this->getLastFromDB();
        if (!$rates) {
            return 0;
        }
        $rates = $rates->getToque();

        return $rates[$from] ?? 0;
    }

    public function getOneToque(string $from = 'EUR'): array
    {
        if ($from == 'EUR') {
            $from = 'ECU';
        }

        $rates = $this->getLastFromDB();
        if (!$rates) {
            return [];
        }
        $ratesToque = $rates->getToque();

        return [
            'id' => (string)$rates->getId(),
            'rates' => $ratesToque[$from] ?? '0'
        ];
    }

    public function getAllTasasActual(): array
    {
        $amounEnd = [];
        $rates = $this->getLastFromDB();
        if (!$rates) {
            return [];
        }
        $rates = $rates->getToque();

        foreach ($rates as $key => $rate) {
            if ($key == 'ECU') {
                $key = 'EUR';
            }
            $amounEnd[$key] = $rate;
        }
        return $amounEnd;
    }

    /**
     * Se ejecuta solo en el CRON → consulta API y guarda en BD si hay cambios.
     */
    public function saveTasasActual(EntityManagerInterface $entityManager): array
    {
        try {
            $toques = $this->getExchangeRates(); // trae ['tasas'=>[], 'date'=>...]
            $toquesBD = $this->getAllTasasActual(); // trae último guardado, con EUR en vez de ECU

            $insert = false;

            foreach ($toques['tasas'] as $key => $toque) {
                if (!isset($toquesBD[$key]) || $toques['tasas'][$key] !== $toquesBD[$key]) {
                    $insert = true;
                    break;
                }
            }

            if ($insert || empty($toquesBD)) {
                $toque = new Toque();
                // guardamos ya con el formato de la API (incluye ECU)
                $toque->setToque($toques['tasas']);
                $entityManager->persist($toque);
                $entityManager->flush();
                $toquesBD = $toque->getToque();
            }

            return $toquesBD;
        } catch (\Exception $e) {
            return ['error' => 'Error al guardar los datos: ' . $e->getMessage()];
        }
    }


    public function getByIdFromKey(string $id = null, string $from = null): array
    {
        if (!$id || !$from) {
            return [];
        }
        $rates = $this->getLastFromDB(['id' => $id]);
        if ($from === 'EUR') {
            $from = 'ECU';
        }
        $ratesToque = $rates->getToque();
        return [
            'id' => (string)$rates->getId(),
            'rates' => $ratesToque[$from] ?? '0'
        ];
    }

    public function getCalculateValueForId(float $amount, string $from = 'ECU', string $id): array
    {
        $amounEnd = [];
        $rates = $this->getLastFromDB(['id' => $id]);
        if ($from === 'EUR') {
            $from = 'ECU';
        }
        if (!$rates) {
            return ['error' => 'No hay tasas en la base de datos'];
        }
        $rates = $rates->getToque();
        // APLICO EL CALCULO PARA CUP
        $cupAmount = $amount * $rates[$from];
        $amounEnd['CUP'] = $cupAmount;
        // FIN APLICO EL CALCULO PARA CUP

        foreach ($rates as $key => $rate) {
            $calculate = ($amount * $rates[$from]) / $rates[$key];
            $amounEnd[$key] = $calculate;
        }

        return $amounEnd;

    }

    public function getOneCalculateValueForId(float $amount, string $from = 'ECU', string $id): float
    {
        $rates = $this->getLastFromDB(['id' => $id]);
        if ($from === 'EUR') {
            $from = 'ECU';
        }
        $rates = $rates->getToque();
        $calculate = $amount * $rates[$from];

        return round($calculate, 2) ?? 0;
    }

    public function getToqueCurrencyEnabled()
    {
        $currencies = $this->entityManager
            ->getRepository(Currency::class)
            ->findBy(['isActive' => true], ['createdAt' => 'DESC']);

        return array_map(fn($s) => [
            $s->getSiglas() => (float) $this->getOneTasasActual($s->getSiglas())
        ], $currencies);

    }

}

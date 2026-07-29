<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRate;
use App\Repository\Balance\CurrencyRepository;
use App\Repository\Balance\ExchangeRateSnapshotRepository;
use App\Security\Attribute\RequireAnyScope;
use App\Security\Attribute\RequireScope;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Util\BcMath;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Exchange Rates', description: 'Tipos de cambio')]
class ExchangeRateController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private APIExchangeRate $apiExchangeRate,
        private CurrencyRepository $currencyRepository,
        private ExchangeRateSnapshotRepository $snapshotRepository,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/exchange-rates',
        summary: 'Listar tipos de cambio',
        description: 'Obtiene los tipos de cambio, opcionalmente filtrados por proveedor. Si no hay tasas en base, cae al último snapshot fetcheado.',
        tags: ['Exchange Rates'],
    )]
    #[OA\Parameter(name: 'providerId', in: 'query', description: 'Filtrar por ID de proveedor', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 100))]
    #[OA\Response(
        response: 200,
        description: 'Lista de tipos de cambio',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'er1-...'),
                        new OA\Property(property: 'providerId', type: 'string', example: 'ep1-...'),
                        new OA\Property(property: 'providerName', type: 'string', example: 'BCV'),
                        new OA\Property(property: 'fromCurrency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'toCurrency', type: 'string', example: 'VES'),
                        new OA\Property(property: 'rate', type: 'string', example: '36.50'),
                        new OA\Property(property: 'inverseRate', type: 'string', example: '0.0274'),
                        new OA\Property(property: 'fetchedAt', type: 'string', example: '2024-01-01 12:00:00'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    ]
                ))
            ]
        )
    )]
    #[RequireAnyScope('exchange_rates.read', 'exchange_rates_admin.read')]
    #[Route('/exchange-rates', name: 'api_exchange_rate_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $providerId = $request->query->get('providerId');
        $activeCodes = $this->currencyRepository->findActiveCodes();
        if (empty($activeCodes)) {
            return $this->success([]);
        }

        // Solo se muestran tasas de monedas activas en el nomenclador (App\Entity\Balance\Currency).
        $criteria = ['toCurrency' => $activeCodes];
        if ($providerId) {
            $criteria['provider'] = $providerId;
        }
        $rates = $this->entityManager->getRepository(ExchangeRate::class)->findBy(
            $criteria,
            ['isActive' => 'DESC', 'fetchedAt' => 'DESC'],
            $request->query->getInt('limit', 100)
        );

        if (empty($rates)) {
            return $this->success($this->buildFromSnapshot($activeCodes));
        }

        $data = array_values(array_map(fn(ExchangeRate $r) => [
            'id' => $r->getId(),
            'providerId' => $r->getProvider()?->getId(),
            'providerName' => $r->getProvider()?->getName() ?? 'Manual',
            'fromCurrency' => $r->getFromCurrency(),
            'toCurrency' => $r->getToCurrency(),
            'rate' => $r->getRate(),
            'inverseRate' => $r->getInverseRate(),
            'fetchedAt' => $r->getFetchedAt()->format('Y-m-d H:i:s'),
            'isActive' => $r->isActive(),
            'isLocked' => $r->isLocked(),
            'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $rates));
        return $this->success($data);
    }

    #[OA\Get(
        path: '/api/v1/exchange-rate/convert',
        summary: 'Convertir moneda',
        description: 'Convierte un monto de una moneda origen a la moneda base del sistema usando la tasa vigente.',
        tags: ['Exchange Rates'],
    )]
    #[OA\Parameter(name: 'amount', in: 'query', description: 'Monto a convertir', required: true, schema: new OA\Schema(type: 'number'))]
    #[OA\Parameter(name: 'currency', in: 'query', description: 'Código de moneda origen', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Resultado de la conversión')]
    #[RequireAnyScope('exchange_rates.read', 'exchange_rates_admin.read')]
    #[Route('/exchange-rate/convert', name: 'api_exchange_rate_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        try {
            $amount = $request->query->get('amount');
            $currency = strtoupper($request->query->get('currency', ''));

            if (!$amount || !$currency) {
                return $this->error('amount y currency son requeridos');
            }

            return $this->success($this->apiExchangeRate->convertToBase((string) $amount, $currency));
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/exchange-rates/fetch', summary: 'Obtener tasas nuevas del proveedor activo (admin)', tags: ['Exchange Rates'])]
    #[OA\Response(response: 200, description: 'Tasas actualizadas')]
    #[RequireScope('exchange_rates_admin.fetch')]
    #[Route('/exchange-rates/fetch', name: 'api_exchange_rate_fetch', methods: ['POST'])]
    public function fetch(): JsonResponse
    {
        try {
            $result = $this->apiExchangeRate->refreshRates();
            if ($result['success']) {
                return $this->success(null, 'Tasas actualizadas correctamente');
            }

            return $this->error($result['error'] ?? 'Error al obtener tasas');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(path: '/api/v1/exchange-rates/manual', summary: 'Registrar tasa manual (admin)', tags: ['Exchange Rates'])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['toCurrency', 'rate'],
            properties: [
                new OA\Property(property: 'toCurrency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'rate', type: 'string', example: '0.92'),
                new OA\Property(property: 'locked', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Tasa manual registrada')]
    #[RequireScope('exchange_rates_admin.fetch')]
    #[Route('/exchange-rates/manual', name: 'api_exchange_rate_manual_create', methods: ['POST'])]
    public function createManual(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $toCurrency = strtoupper((string) ($data['toCurrency'] ?? ''));
            $rate = (string) ($data['rate'] ?? '');
            $locked = (bool) ($data['locked'] ?? false);

            if (!$toCurrency || !$rate) {
                return $this->error('toCurrency y rate son requeridos');
            }

            $this->apiExchangeRate->createManualRate($toCurrency, $rate, $locked);

            return $this->success(null, 'Tasa manual registrada', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function buildFromSnapshot(array $activeCodes): array
    {
        $snapshot = $this->snapshotRepository->findLast();
        if (!$snapshot) {
            return [];
        }

        $base = $snapshot->getBase();
        $data = [];

        foreach ($snapshot->getRates() as $currency => $rate) {
            if ($currency === $base || !in_array($currency, $activeCodes, true)) {
                continue;
            }
            $data[] = [
                'id' => $snapshot->getId(),
                'providerId' => null,
                'providerName' => $base . ' API',
                'fromCurrency' => $base,
                'toCurrency' => $currency,
                'rate' => (string) $rate,
                'inverseRate' => $rate > 0 ? BcMath::round(bcdiv('1', (string) $rate, 12), 8) : null,
                'fetchedAt' => $snapshot->getCreatedAt()?->format('Y-m-d H:i:s'),
                'isActive' => true,
                'isLocked' => false,
                'createdAt' => $snapshot->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $data;
    }
}

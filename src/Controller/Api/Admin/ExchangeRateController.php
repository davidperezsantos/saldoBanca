<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRate;
use App\Repository\Balance\CurrencyRepository;
use App\Repository\Balance\ExchangeRateSnapshotRepository;
use App\Security\Attribute\RequireScope;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Util\BcMath;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Historial de tasas de cambio desde mobile/ — espejo JSON de Controller/Admin/ExchangeRateController.
 */
#[OA\Tag(name: 'Admin Exchange Rates', description: 'Historial de tasas de cambio (requiere scope exchange_rates_admin.*)')]
class ExchangeRateController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private APIExchangeRate $apiExchangeRate,
        private ExchangeRateSnapshotRepository $snapshotRepository,
        private CurrencyRepository $currencyRepository,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/exchange-rates', summary: 'Listar tasas de cambio vigentes', tags: ['Admin Exchange Rates'])]
    #[OA\Parameter(name: 'providerId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Tasas de cambio')]
    #[RequireScope('exchange_rates_admin.read')]
    #[Route('/admin/exchange-rates', name: 'api_admin_exchange_rates_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $providerId = $request->query->get('providerId');
        $activeCodes = $this->currencyRepository->findActiveCodes();

        if (empty($activeCodes)) {
            return $this->success([]);
        }

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

        $data = array_map(fn(ExchangeRate $rate) => [
            'id' => $rate->getId(),
            'providerId' => $rate->getProvider()?->getId(),
            'providerName' => $rate->getProvider()?->getName() ?? 'Manual',
            'fromCurrency' => $rate->getFromCurrency(),
            'toCurrency' => $rate->getToCurrency(),
            'rate' => $rate->getRate(),
            'inverseRate' => $rate->getInverseRate(),
            'fetchedAt' => $rate->getFetchedAt()->format('Y-m-d H:i:s'),
            'isActive' => $rate->isActive(),
            'isLocked' => $rate->isLocked(),
            'createdAt' => $rate->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $rates);

        return $this->success($data);
    }

    #[OA\Post(path: '/api/v1/admin/exchange-rates/fetch', summary: 'Obtener tasas nuevas del proveedor activo', tags: ['Admin Exchange Rates'])]
    #[OA\Response(response: 200, description: 'Tasas actualizadas')]
    #[RequireScope('exchange_rates_admin.fetch')]
    #[Route('/admin/exchange-rates/fetch', name: 'api_admin_exchange_rates_fetch', methods: ['POST'])]
    public function fetch(): JsonResponse
    {
        try {
            $result = $this->apiExchangeRate->refreshRates();
            if ($result['success']) {
                return $this->success(null, 'Tasas actualizadas correctamente');
            }

            return $this->error($result['error'] ?? 'Error al obtener tasas');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(path: '/api/v1/admin/exchange-rates/manual', summary: 'Registrar tasa manual', tags: ['Admin Exchange Rates'])]
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
    #[Route('/admin/exchange-rates/manual', name: 'api_admin_exchange_rate_manual_create', methods: ['POST'])]
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

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRate;
use App\Repository\Balance\CurrencyRepository;
use App\Security\Attribute\RequireScope;
use App\Services\ExchangeRate\APIExchangeRate;
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
    ) {
    }

    #[OA\Get(
        path: '/api/v1/exchange-rates',
        summary: 'Listar tipos de cambio',
        description: 'Obtiene los tipos de cambio, opcionalmente filtrados por proveedor.',
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
    #[RequireScope('exchange_rates.read')]
    #[Route('/exchange-rates', name: 'api_exchange_rate_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $providerId = $request->query->get('providerId');
        $activeCodes = $this->currencyRepository->findActiveCodes();
        if (empty($activeCodes)) {
            return $this->success([]);
        }

        // Solo se muestran tasas de monedas activas en el nomenclador (App\Entity\Balance\Currency).
        // El filtro por toCurrency va en la query, antes del limit (ver Admin\ExchangeRateController::list).
        $criteria = ['toCurrency' => $activeCodes];
        if ($providerId) {
            $criteria['provider'] = $providerId;
        }
        $rates = $this->entityManager->getRepository(ExchangeRate::class)->findBy(
            $criteria,
            ['fetchedAt' => 'DESC'],
            $request->query->getInt('limit', 100)
        );
        $data = array_values(array_map(fn($r) => [
            'id' => $r->getId(),
            'providerId' => $r->getProvider()?->getId(),
            'providerName' => $r->getProvider()?->getName() ?? 'Manual',
            'fromCurrency' => $r->getFromCurrency(),
            'toCurrency' => $r->getToCurrency(),
            'rate' => $r->getRate(),
            'inverseRate' => $r->getInverseRate(),
            'fetchedAt' => $r->getFetchedAt()->format('Y-m-d H:i:s'),
            'isActive' => $r->isActive(),
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
    #[RequireScope('exchange_rates.read')]
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
}

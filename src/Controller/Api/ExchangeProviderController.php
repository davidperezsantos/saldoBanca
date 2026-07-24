<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRateProvider;
use App\Security\Attribute\RequireScope;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Exchange Providers', description: 'Proveedores de tipo de cambio')]
class ExchangeProviderController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/exchange-providers',
        summary: 'Listar proveedores de tipo de cambio',
        description: 'Obtiene el listado de proveedores de tipo de cambio disponibles.',
        tags: ['Exchange Providers'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Lista de proveedores de tipo de cambio',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'ep1-...'),
                        new OA\Property(property: 'name', type: 'string', example: 'BCV'),
                        new OA\Property(property: 'code', type: 'string', example: 'BCV'),
                        new OA\Property(property: 'apiUrl', type: 'string', example: 'https://api.bcv.org.ve/'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'isDefault', type: 'boolean', example: true),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireScope('exchange_providers.read')]
    #[Route('/exchange-providers', name: 'api_exchange_provider_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $providers = $this->entityManager->getRepository(ExchangeRateProvider::class)->findAll();
        $data = array_map(fn($p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'code' => $p->getCode(),
            'apiUrl' => $p->getApiUrl(),
            'status' => $p->getStatus(),
            'isActive' => $p->isActive(),
            'createdAt' => $p->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $providers);
        return $this->success($data);
    }
}

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRateProvider;
use App\Repository\Balance\ExchangeRateProviderRepository;
use App\Security\Attribute\RequireAnyScope;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Exchange Providers', description: 'Proveedores de tipo de cambio')]
class ExchangeProviderController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExchangeRateProviderRepository $providerRepository,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/exchange-providers',
        summary: 'Listar proveedores de tipo de cambio',
        description: 'Obtiene el listado de proveedores de tipo de cambio disponibles. Un caller con scope exchange_providers_admin.read ve además la configuración de credenciales (apiKey, auth, config).',
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
    #[RequireAnyScope('exchange_providers.read', 'exchange_providers_admin.read')]
    #[Route('/admin/exchange-providers', name: 'api_exchange_provider_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $isAdmin = $this->scopeAuthorizationService->hasScope('exchange_providers_admin.read');
        $providers = $this->providerRepository->findAll();

        if (!$isAdmin) {
            $data = array_map(fn(ExchangeRateProvider $p) => [
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

        // Caller admin: incluye metadata de credenciales (nunca el secreto real en texto plano).
        $data = array_map(fn(ExchangeRateProvider $p) => [
            'id' => $p->getId(),
            'name' => $p->getName(),
            'code' => $p->getCode(),
            'apiUrl' => $p->getApiUrl(),
            'apiKey' => $p->getApiKey(),
            'username' => $p->getUsername(),
            'password' => $p->getPassword() ? '********' : null,
            'token' => $p->getToken() ? '********' : null,
            'authType' => $p->getAuthType(),
            'config' => $p->getConfig(),
            'status' => $p->getStatus(),
            'isActive' => $p->isActive(),
            'createdAt' => $p->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $providers);

        return $this->success($data);
    }

    #[OA\Post(path: '/api/v1/admin/exchange-providers', summary: 'Crear proveedor (admin)', tags: ['Exchange Providers'])]
    #[OA\Response(response: 201, description: 'Proveedor creado')]
    #[RequireScope('exchange_providers_admin.create')]
    #[Route('/admin/exchange-providers', name: 'api_exchange_provider_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);

            if (isset($data['isActive']) && $data['isActive']) {
                $this->deactivateAll();
            }

            $provider = new ExchangeRateProvider();
            $this->setProviderData($provider, $data);

            $this->entityManager->persist($provider);
            $this->entityManager->flush();

            return $this->success(['id' => $provider->getId()], 'Provider created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/exchange-providers/{id}', summary: 'Actualizar proveedor (admin)', tags: ['Exchange Providers'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Proveedor actualizado')]
    #[RequireScope('exchange_providers_admin.update')]
    #[Route('/admin/exchange-providers/{id}', name: 'api_exchange_provider_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $provider = $this->providerRepository->find($id);
            if (!$provider) {
                return $this->error('Provider not found', 404);
            }

            $data = $this->getJsonContent($request);

            if (isset($data['isActive']) && $data['isActive']) {
                $this->deactivateAll();
            }

            $this->setProviderData($provider, $data);
            $this->entityManager->flush();

            return $this->success(null, 'Provider updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/exchange-providers/{id}/status', summary: 'Cambiar estado de un proveedor (admin)', tags: ['Exchange Providers'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[RequireScope('exchange_providers_admin.update')]
    #[Route('/admin/exchange-providers/{id}/status', name: 'api_exchange_provider_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        try {
            $provider = $this->providerRepository->find($id);
            if (!$provider) {
                return $this->error('Provider not found', 404);
            }

            $data = $this->getJsonContent($request);
            $newStatus = $data['status'] ?? 'active';
            $provider->setStatus($newStatus);

            if ($newStatus !== 'active') {
                $provider->setIsActive(false);
            }

            $this->entityManager->flush();

            return $this->success(null, 'Status updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function setProviderData(ExchangeRateProvider $provider, array $data): void
    {
        if (isset($data['name'])) $provider->setName($data['name']);
        if (isset($data['code'])) $provider->setCode($data['code']);
        if (isset($data['apiUrl'])) $provider->setApiUrl($data['apiUrl']);
        if (isset($data['apiKey'])) $provider->setApiKey($data['apiKey']);
        if (isset($data['username'])) $provider->setUsername($data['username']);
        if (isset($data['password'])) $provider->setPassword($data['password']);
        if (isset($data['token'])) $provider->setToken($data['token']);
        if (isset($data['authType'])) $provider->setAuthType($data['authType']);
        if (array_key_exists('isActive', $data)) $provider->setIsActive((bool) $data['isActive']);
        if (isset($data['status'])) $provider->setStatus($data['status']);
        if (isset($data['config'])) $provider->setConfig($data['config']);
    }

    private function deactivateAll(): void
    {
        foreach ($this->providerRepository->findBy(['isActive' => true]) as $p) {
            $p->setIsActive(false);
        }
        $this->entityManager->flush();
    }
}

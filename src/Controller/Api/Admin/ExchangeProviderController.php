<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRateProvider;
use App\Repository\Balance\ExchangeRateProviderRepository;
use App\Security\Attribute\RequireScope;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Proveedores de tasas de cambio desde mobile/ — espejo JSON de Controller/Admin/ExchangeProviderController.
 */
#[OA\Tag(name: 'Admin Exchange Providers', description: 'Proveedores de tasas de cambio (requiere scope exchange_providers_admin.*)')]
class ExchangeProviderController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExchangeRateProviderRepository $providerRepository,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/exchange-providers', summary: 'Listar proveedores de tasas', tags: ['Admin Exchange Providers'])]
    #[OA\Response(response: 200, description: 'Lista de proveedores')]
    #[RequireScope('exchange_providers_admin.read')]
    #[Route('/admin/exchange-providers', name: 'api_admin_exchange_providers_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $providers = $this->providerRepository->findAll();

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

    #[OA\Post(path: '/api/v1/admin/exchange-providers', summary: 'Crear proveedor', tags: ['Admin Exchange Providers'])]
    #[OA\Response(response: 201, description: 'Proveedor creado')]
    #[RequireScope('exchange_providers_admin.create')]
    #[Route('/admin/exchange-providers', name: 'api_admin_exchange_provider_create', methods: ['POST'])]
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
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(path: '/api/v1/admin/exchange-providers/{id}', summary: 'Actualizar proveedor', tags: ['Admin Exchange Providers'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Proveedor actualizado')]
    #[RequireScope('exchange_providers_admin.update')]
    #[Route('/admin/exchange-providers/{id}', name: 'api_admin_exchange_provider_update', methods: ['PUT'])]
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
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(path: '/api/v1/admin/exchange-providers/{id}/status', summary: 'Cambiar estado de un proveedor', tags: ['Admin Exchange Providers'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[RequireScope('exchange_providers_admin.update')]
    #[Route('/admin/exchange-providers/{id}/status', name: 'api_admin_exchange_provider_status', methods: ['PUT'])]
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
            return $this->error($e->getMessage(), 400);
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

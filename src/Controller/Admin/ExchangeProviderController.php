<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\ExchangeRateProvider;
use App\Repository\Balance\ExchangeRateProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/exchange-providers')]
class ExchangeProviderController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ExchangeRateProviderRepository $providerRepository,
    ) {
    }

    #[Route('', name: 'admin_exchange_providers_page', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('exchange.providers:view');

        return $this->render('admin/exchange_providers.html.twig');
    }

    #[Route('/list', name: 'admin_exchange_providers_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.providers:view');

        $providers = $this->providerRepository->findAll();

        $data = array_map(function (ExchangeRateProvider $p) {
            return [
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
            ];
        }, $providers);

        return $this->success($data);
    }

    #[Route('', name: 'admin_exchange_provider_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.providers:create');

        try {
            $this->validateCsrfToken();
            $data = json_decode($request->getContent(), true);

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

    #[Route('/{id}', name: 'admin_exchange_provider_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.providers:edit');

        try {
            $this->validateCsrfToken();
            $provider = $this->providerRepository->find($id);
            if (!$provider) {
                return $this->error('Provider not found', 404);
            }

            $data = json_decode($request->getContent(), true);

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

    #[Route('/{id}/status', name: 'admin_exchange_provider_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('exchange.providers:edit');

        try {
            $this->validateCsrfToken();
            $provider = $this->providerRepository->find($id);
            if (!$provider) {
                return $this->error('Provider not found', 404);
            }

            $data = json_decode($request->getContent(), true);
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
        if (array_key_exists('isActive', $data)) $provider->setIsActive((bool)$data['isActive']);
        if (isset($data['status'])) $provider->setStatus($data['status']);
        if (isset($data['config'])) $provider->setConfig($data['config']);
    }

    private function deactivateAll(): void
    {
        $activeProviders = $this->providerRepository->findBy(['isActive' => true]);
        foreach ($activeProviders as $p) {
            $p->setIsActive(false);
        }
        $this->entityManager->flush();
    }
}

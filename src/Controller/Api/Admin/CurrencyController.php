<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\Currency;
use App\Security\Attribute\RequireScope;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Nomenclador de monedas desde mobile/ — espejo JSON de Controller/Admin/CurrencyController.
 */
#[OA\Tag(name: 'Admin Currencies', description: 'Nomenclador de monedas (requiere scope currencies_admin.*)')]
class CurrencyController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/currencies', summary: 'Listar monedas', tags: ['Admin Currencies'])]
    #[OA\Parameter(name: 'active', in: 'query', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Lista de monedas')]
    #[RequireScope('currencies_admin.read')]
    #[Route('/admin/currencies', name: 'api_admin_currencies_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $repository = $this->entityManager->getRepository(Currency::class);
        $currencies = $request->query->getBoolean('active')
            ? $repository->findActive()
            : $repository->findBy([], ['code' => 'ASC']);

        return $this->success(array_map(fn(Currency $c) => [
            'id' => $c->getId(),
            'code' => $c->getCode(),
            'name' => $c->getName(),
            'symbol' => $c->getSymbol(),
            'isActive' => $c->isActive(),
        ], $currencies));
    }

    #[OA\Post(path: '/api/v1/admin/currencies', summary: 'Crear moneda', tags: ['Admin Currencies'])]
    #[OA\Response(response: 201, description: 'Moneda creada')]
    #[OA\Response(response: 409, description: 'La moneda ya existe')]
    #[RequireScope('currencies_admin.create')]
    #[Route('/admin/currencies', name: 'api_admin_currency_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $code = strtoupper(trim($data['code'] ?? ''));
            $name = trim($data['name'] ?? '');

            if ($code === '' || $name === '') {
                return $this->error('code y name son requeridos', 422);
            }

            if ($this->entityManager->getRepository(Currency::class)->findOneBy(['code' => $code])) {
                return $this->error("La moneda {$code} ya existe", 409);
            }

            $currency = new Currency();
            $currency->setCode($code);
            $currency->setName($name);
            $currency->setSymbol($data['symbol'] ?? null);
            $currency->setIsActive($data['isActive'] ?? true);

            $this->entityManager->persist($currency);
            $this->entityManager->flush();

            return $this->success(['id' => $currency->getId()], 'Currency created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/currencies/{id}', summary: 'Actualizar moneda', tags: ['Admin Currencies'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Moneda actualizada')]
    #[RequireScope('currencies_admin.update')]
    #[Route('/admin/currencies/{id}', name: 'api_admin_currency_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $currency = $this->entityManager->getRepository(Currency::class)->find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }

        $data = $this->getJsonContent($request);
        $currency->setName(trim($data['name'] ?? $currency->getName()));
        $currency->setSymbol($data['symbol'] ?? $currency->getSymbol());

        $this->entityManager->flush();

        return $this->success(null, 'Currency updated');
    }

    #[OA\Put(path: '/api/v1/admin/currencies/{id}/status', summary: 'Cambiar estado de una moneda', tags: ['Admin Currencies'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[RequireScope('currencies_admin.status')]
    #[Route('/admin/currencies/{id}/status', name: 'api_admin_currency_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        $currency = $this->entityManager->getRepository(Currency::class)->find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }

        $data = $this->getJsonContent($request);
        $currency->setIsActive((bool) ($data['isActive'] ?? !$currency->isActive()));

        $this->entityManager->flush();

        return $this->success(['isActive' => $currency->isActive()], 'Status updated');
    }
}

<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\Currency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Nomenclador de monedas: qué monedas opera el sistema. Los selects de moneda del resto del panel
 * (recarga, transferencia, cuentas) y el listado de tasas de cambio filtran contra `isActive` de
 * aquí, en vez de traer una lista fija de códigos hardcodeada en cada componente.
 */
#[Route('/currencies')]
class CurrencyController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_currencies_page', methods: ['GET'])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted('currencies:view');

        return $this->render('admin/currencies.html.twig');
    }

    #[Route('/list', name: 'admin_currency_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('currencies:view');

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

    #[Route('', name: 'admin_currency_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('currencies:create');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $code = strtoupper(trim($data['code'] ?? ''));
            $name = trim($data['name'] ?? '');

            if ($code === '' || $name === '') {
                return $this->error('code y name son requeridos', 422);
            }

            $existing = $this->entityManager->getRepository(Currency::class)->findOneBy(['code' => $code]);
            if ($existing) {
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

    #[Route('/{id}', name: 'admin_currency_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('currencies:edit');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

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

    #[Route('/{id}/status', name: 'admin_currency_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('currencies:status');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 400);
        }

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

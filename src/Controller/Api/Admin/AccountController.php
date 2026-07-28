<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\AccountDto;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\AccountService;
use App\Services\Balance\BalanceService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\RegistrationService;
use App\Services\SystemCurrencyService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de cuentas (clientes y negocios) de TODO el sistema desde mobile/ — espejo JSON de
 * Controller/Admin/AccountController (panel Twig). No confundir con Controller/Api/AccountController
 * (self-service, "mi propia cuenta"): acá el caller es staff, nunca dueño de la cuenta que consulta.
 */
#[OA\Tag(name: 'Admin Accounts', description: 'Cuentas del sistema (requiere scope accounts_admin.*)')]
class AccountController extends BaseController
{
    public function __construct(
        private AccountService $accountService,
        private SystemCurrencyService $systemCurrencyService,
        private BalanceService $balanceService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/accounts', summary: 'Listar cuentas del sistema', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'accountType', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Lista de cuentas')]
    #[RequireScope('accounts_admin.read')]
    #[Route('/admin/accounts', name: 'api_admin_accounts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        foreach (['status', 'accountType', 'search'] as $key) {
            if ($request->query->has($key)) {
                $filters[$key] = $request->query->get($key);
            }
        }

        $accounts = $this->accountService->listAccounts($filters);
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        $data = array_map(function ($account) use ($baseCurrency) {
            $balance = $this->balanceService->getBalance($account->getId()->toString(), $baseCurrency);
            $available = $balance ? $balance->getAvailableBalance() : '0.00';
            $displayCurrency = $account->getDefaultCurrency();

            if ($displayCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($displayCurrency);
                if ($rate !== null) {
                    $available = (string) round((float) bcmul($available, (string) $rate, 4), 2);
                }
            }

            return [
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'accountType' => $account->getAccountType(),
                'businessName' => $account->getBusinessName(),
                'documentType' => $account->getDocumentType(),
                'documentNumber' => $account->getDocumentNumber(),
                'email' => $account->getEmail(),
                'phone' => $account->getPhone(),
                'status' => $account->getStatus(),
                'defaultCurrency' => $displayCurrency,
                'creditLimit' => $account->getCreditLimit(),
                'saldoDisponible' => $available,
                'saldoBase' => $balance ? $balance->getAvailableBalance() : '0.00',
                'baseCurrency' => $baseCurrency,
                'createdAt' => $account->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $accounts);

        return $this->success($data);
    }

    #[OA\Post(path: '/api/v1/admin/accounts', summary: 'Crear cuenta', tags: ['Admin Accounts'])]
    #[OA\Response(response: 201, description: 'Cuenta creada')]
    #[RequireScope('accounts_admin.create')]
    #[Route('/admin/accounts', name: 'api_admin_accounts_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = AccountDto::fromJson($this->getJsonContent($request));
            $account = $this->accountService->createAccount($dto);

            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/admin/accounts/{id}', summary: 'Detalle de una cuenta', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Resumen de la cuenta')]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('accounts_admin.read')]
    #[Route('/admin/accounts/{id}', name: 'api_admin_accounts_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $account = $this->accountService->getAccount($id);
        if (!$account) {
            return $this->error('Account not found', 404);
        }

        return $this->success($this->accountService->getAccountSummary($id));
    }

    #[OA\Put(path: '/api/v1/admin/accounts/{id}', summary: 'Actualizar cuenta', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Cuenta actualizada')]
    #[RequireScope('accounts_admin.update')]
    #[Route('/admin/accounts/{id}', name: 'api_admin_accounts_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $dto = AccountDto::fromJson($this->getJsonContent($request));
            $account = $this->accountService->updateAccount($id, $dto);

            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account updated successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/accounts/{id}/status', summary: 'Cambiar estado de una cuenta', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[RequireScope('accounts_admin.status')]
    #[Route('/admin/accounts/{id}/status', name: 'api_admin_accounts_status', methods: ['PUT'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? null;
            if (!$status) {
                return $this->error('Status is required');
            }

            $account = $this->accountService->changeStatus($id, $status);

            return $this->success([
                'id' => $account->getId(),
                'status' => $account->getStatus(),
            ], 'Account status updated successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/admin/accounts/{id}/balance', summary: 'Saldo de una cuenta', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Saldo de la cuenta')]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('accounts_admin.balance')]
    #[Route('/admin/accounts/{id}/balance', name: 'api_admin_accounts_balance', methods: ['GET'])]
    public function balance(string $id): JsonResponse
    {
        $account = $this->accountService->getAccount($id);
        if (!$account) {
            return $this->error('Account not found', 404);
        }

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $balance = $this->balanceService->getBalance($id, $baseCurrency);

        $available = $balance ? $balance->getAvailableBalance() : '0.00';
        $pending = $balance ? $balance->getPendingBalance() : '0.00';
        $reserved = $balance ? $balance->getReservedBalance() : '0.00';
        $displayCurrency = $account->getDefaultCurrency();

        if ($displayCurrency !== $baseCurrency) {
            $rate = $this->apiExchangeRate->getRate($displayCurrency);
            if ($rate !== null) {
                $available = bcmul($available, (string) $rate, 2);
                $pending = bcmul($pending, (string) $rate, 2);
                $reserved = bcmul($reserved, (string) $rate, 2);
            }
        }

        return $this->success([
            'available' => $available,
            'pending' => $pending,
            'reserved' => $reserved,
            'currency' => $displayCurrency,
            'totalRecharged' => $balance ? $balance->getTotalRecharged() : '0.00',
            'totalTransferred' => $balance ? $balance->getTotalTransferred() : '0.00',
            'totalInvoiced' => $balance ? $balance->getTotalInvoiced() : '0.00',
        ]);
    }

    #[OA\Post(path: '/api/v1/admin/accounts/{id}/approve', summary: 'Aprobar negocio pendiente', tags: ['Admin Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Negocio aprobado')]
    #[RequireScope('accounts_admin.approve_business')]
    #[Route('/admin/accounts/{id}/approve', name: 'api_admin_accounts_approve', methods: ['POST'])]
    public function approveBusiness(string $id, Request $request, RegistrationService $registrationService, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        try {
            $user = $registrationService->approveBusiness($id, $this->getJsonContent($request));

            return $this->success([
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
            ], 'Business approved successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

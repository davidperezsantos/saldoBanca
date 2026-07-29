<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\AccountDto;
use App\Security\Attribute\RequireAnyScope;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\AccountService;
use App\Services\Balance\BalanceService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\RegistrationService;
use App\Services\SystemCurrencyService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Accounts', description: 'Gestión de cuentas bancarias')]
class AccountController extends BaseController
{
    public function __construct(
        private AccountService $accountService,
        private ScopeAuthorizationService $scopeAuthorizationService,
        #[Autowire(service: 'limiter.pin_request')]
        private RateLimiterFactory $pinRequestLimiter,
        private SystemCurrencyService $systemCurrencyService,
        private BalanceService $balanceService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/accounts',
        summary: 'Listar cuentas',
        description: 'Un caller self-service (JWT sin scope admin) solo ve su propia cuenta. Un caller con scope accounts_admin.read ve todas las cuentas del sistema, con saldo convertido a su moneda.',
        tags: ['Accounts'],
    )]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros por página', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Desplazamiento para paginación', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Parameter(name: 'accountType', in: 'query', description: 'Filtrar por tipo de cuenta (checking, savings, etc.)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (active, inactive, suspended)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Buscar por nombre o número de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Lista de cuentas',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'a1b2c3d4-...'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'accountType', type: 'string', example: 'checking'),
                        new OA\Property(property: 'businessName', type: 'string', example: 'Empresa S.A.'),
                        new OA\Property(property: 'documentType', type: 'string', example: 'RUC'),
                        new OA\Property(property: 'documentNumber', type: 'string', example: '1234567890'),
                        new OA\Property(property: 'email', type: 'string', example: 'empresa@ejemplo.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+123456789'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'defaultCurrency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireAnyScope('accounts.read', 'accounts_admin.read')]
    #[Route('/accounts', name: 'api_account_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $isAdmin = $this->scopeAuthorizationService->hasScope('accounts_admin.read');
        $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();

        // Usuario final self-service (JWT sin scope admin): solo puede ver SU propia cuenta.
        // Un cliente OAuth2 de negocio o un caller con accounts_admin.read ven el listado completo.
        if ($selfServiceUser !== null && !$isAdmin) {
            $account = $selfServiceUser->getAccount();
            $accounts = $account !== null ? [$account] : [];
        } else {
            $filters = [
                'limit' => $request->query->getInt('limit', 20),
                'offset' => $request->query->getInt('offset', 0),
            ];
            foreach (['accountType', 'status', 'search'] as $key) {
                if ($request->query->has($key)) {
                    $filters[$key] = $request->query->get($key);
                }
            }

            $accounts = $this->accountService->listAccounts($filters);
        }

        if (!$isAdmin) {
            $data = array_map(fn($a) => [
                'id' => $a->getId(),
                'accountNumber' => $a->getAccountNumber(),
                'accountType' => $a->getAccountType(),
                'businessName' => $a->getBusinessName(),
                'documentType' => $a->getDocumentType(),
                'documentNumber' => $a->getDocumentNumber(),
                'email' => $a->getEmail(),
                'phone' => $a->getPhone(),
                'status' => $a->getStatus(),
                'defaultCurrency' => $a->getDefaultCurrency(),
                'maxPerTransfer' => $a->getMaxPerTransfer(),
                'maxDaily' => $a->getMaxDaily(),
                'maxMonthly' => $a->getMaxMonthly(),
                'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $accounts);

            return $this->success($data);
        }

        // Caller admin: enriquece con saldo convertido a la moneda de cada cuenta.
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
                'maxPerTransfer' => $account->getMaxPerTransfer(),
                'maxDaily' => $account->getMaxDaily(),
                'maxMonthly' => $account->getMaxMonthly(),
                'creditLimit' => $account->getCreditLimit(),
                'saldoDisponible' => $available,
                'saldoBase' => $balance ? $balance->getAvailableBalance() : '0.00',
                'baseCurrency' => $baseCurrency,
                'createdAt' => $account->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $accounts);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/accounts',
        summary: 'Crear cuenta',
        description: 'Crea una nueva cuenta bancaria.',
        tags: ['Accounts'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['businessName', 'documentNumber'],
            properties: [
                new OA\Property(property: 'accountType', description: 'Tipo de cuenta', type: 'string', example: 'checking'),
                new OA\Property(property: 'businessName', description: 'Razón social o nombre', type: 'string', example: 'Empresa S.A.'),
                new OA\Property(property: 'documentType', description: 'Tipo de documento', type: 'string', example: 'RUC'),
                new OA\Property(property: 'documentNumber', description: 'Número de documento', type: 'string', example: '1234567890'),
                new OA\Property(property: 'email', description: 'Correo electrónico', type: 'string', example: 'empresa@ejemplo.com'),
                new OA\Property(property: 'phone', description: 'Teléfono', type: 'string', example: '+123456789'),
                new OA\Property(property: 'maxPerTransfer', description: 'Monto máximo por transferencia (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'maxDaily', description: 'Límite diario de transferencias (opcional)', type: 'number', example: 50000.00),
                new OA\Property(property: 'maxMonthly', description: 'Límite mensual de transferencias (opcional)', type: 'number', example: 500000.00),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Cuenta creada exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Account created'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'a1b2c3d4-...'),
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'businessName', type: 'string', example: 'Empresa S.A.'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 401, description: 'Token OAuth2 inválido o ausente')]
    #[OA\Response(response: 403, description: 'Scope insuficiente')]
    #[RequireAnyScope('accounts.create', 'accounts_admin.create')]
    #[Route('/accounts', name: 'api_account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = AccountDto::fromJson($data);
            $account = $this->accountService->createAccount($dto);
            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/accounts/{id}/request-pin',
        summary: 'Solicitar código de verificación',
        description: 'Genera un código de un solo uso y lo envía por WhatsApp al teléfono de la cuenta — usarlo luego en PUT /invoices/{id}/pay para pagar una factura.',
        tags: ['Accounts'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Código enviado por WhatsApp')]
    #[OA\Response(response: 400, description: 'Sin teléfono configurado o cooldown activo (esperar 60s entre pedidos)')]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('accounts.request_pin')]
    #[Route('/accounts/{id}/request-pin', name: 'api_account_request_pin', methods: ['POST'])]
    public function requestPin(string $id, Request $request): JsonResponse
    {
        $limiter = $this->pinRequestLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->error('Demasiados intentos. Probá de nuevo en un minuto.', 429);
        }

        try {
            $account = $this->accountService->getAccount($id);
            if (!$account) {
                return $this->error('Account not found', 404);
            }
            if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($account)) {
                return $this->error('No podés solicitar un PIN para esta cuenta', 403);
            }
            $this->accountService->requestPin($account);
            return $this->success(null, 'Código enviado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/accounts/{number}',
        summary: 'Obtener cuenta por número',
        description: 'Devuelve los detalles de una cuenta incluyendo su resumen (saldos, límites).',
        tags: ['Accounts'],
    )]
    #[OA\Parameter(name: 'number', in: 'path', description: 'Número de cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Detalle de la cuenta',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'businessName', type: 'string', example: 'Empresa S.A.'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('accounts.read')]
    #[Route('/accounts/{number}', name: 'api_account_show', methods: ['GET'])]
    public function show(string $number): JsonResponse
    {
        try {
            $account = $this->accountService->getAccountByNumber($number);
            if (!$account) {
                return $this->error('Account not found', 404);
            }
            $summary = $this->accountService->getAccountSummary($account->getId()->toString());
            return $this->success($summary);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/accounts/{id}',
        summary: 'Actualizar cuenta',
        description: 'Actualiza los datos de una cuenta existente.',
        tags: ['Accounts'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['businessName', 'documentNumber'],
            properties: [
                new OA\Property(property: 'accountType', type: 'string', example: 'checking'),
                new OA\Property(property: 'businessName', type: 'string', example: 'Empresa S.A.'),
                new OA\Property(property: 'documentType', type: 'string', example: 'RUC'),
                new OA\Property(property: 'documentNumber', type: 'string', example: '1234567890'),
                new OA\Property(property: 'email', type: 'string', example: 'empresa@ejemplo.com'),
                new OA\Property(property: 'phone', type: 'string', example: '+123456789'),
                new OA\Property(property: 'maxPerTransfer', description: 'Monto máximo por transferencia (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'maxDaily', description: 'Límite diario de transferencias (opcional)', type: 'number', example: 50000.00),
                new OA\Property(property: 'maxMonthly', description: 'Límite mensual de transferencias (opcional)', type: 'number', example: 500000.00),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Account updated')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[RequireAnyScope('accounts.update', 'accounts_admin.update')]
    #[Route('/accounts/{id}', name: 'api_account_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = AccountDto::fromJson($data);
            $account = $this->accountService->updateAccount($id, $dto);
            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/accounts/{id}/status',
        summary: 'Cambiar estado de cuenta',
        description: 'Activa, suspende o cierra una cuenta.',
        tags: ['Accounts'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', description: 'Nuevo estado (active, suspended, closed)', type: 'string', example: 'suspended'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated')]
    #[OA\Response(response: 400, description: 'Error al cambiar estado')]
    #[RequireAnyScope('accounts.status', 'accounts_admin.status')]
    #[Route('/accounts/{id}/status', name: 'api_account_status', methods: ['PUT'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? null;
            if (!$status) {
                return $this->error('status is required', 400);
            }
            $account = $this->accountService->changeStatus($id, $status);
            return $this->success([
                'id' => $account->getId(),
                'status' => $account->getStatus(),
            ], 'Account status updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(path: '/api/v1/accounts/{id}/balance', summary: 'Saldo de una cuenta (admin)', tags: ['Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Saldo de la cuenta')]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('accounts_admin.balance')]
    #[Route('/accounts/{id}/balance', name: 'api_account_balance', methods: ['GET'])]
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

    #[OA\Post(path: '/api/v1/accounts/{id}/approve', summary: 'Aprobar negocio pendiente (admin)', tags: ['Accounts'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Negocio aprobado')]
    #[RequireScope('accounts_admin.approve_business')]
    #[Route('/accounts/{id}/approve', name: 'api_account_approve', methods: ['POST'])]
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

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\AccountDto;
use App\Services\Balance\AccountService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Accounts', description: 'Gestión de cuentas bancarias')]
class AccountController extends BaseController
{
    public function __construct(
        private AccountService $accountService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/accounts',
        summary: 'Listar cuentas',
        description: 'Obtiene un listado paginado de cuentas con filtros opcionales.',
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
    #[Route('/accounts', name: 'api_account_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        if ($request->query->has('accountType')) {
            $filters['accountType'] = $request->query->get('accountType');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        if ($request->query->has('search')) {
            $filters['search'] = $request->query->get('search');
        }

        $accounts = $this->accountService->listAccounts($filters);
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
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $accounts);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/accounts',
        summary: 'Crear cuenta',
        description: 'Crea una nueva cuenta bancaria. Requiere API Key.',
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
    #[OA\Response(response: 401, description: 'Invalid API key')]
    #[Route('/accounts', name: 'api_account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->error('Invalid API key', 401);
        }
        try {
            $data = $this->getJsonContent($request);
            $dto = AccountDto::fromJson($data);
            $account = $this->accountService->createAccount($dto);
            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
            ], 'Account created', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
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
            return $this->error($e->getMessage(), 400);
        }
    }
}

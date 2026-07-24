<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\AuthorizedDto;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\AuthorizedService;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authorized', description: 'Usuarios autorizados')]
class AuthorizedController extends BaseController
{
    public function __construct(
        private AuthorizedService $authorizedService,
        private ScopeAuthorizationService $scopeAuthorizationService,
        #[Autowire(service: 'limiter.pin_request')]
        private RateLimiterFactory $pinRequestLimiter,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/authorized',
        summary: 'Listar usuarios autorizados',
        description: 'Obtiene los usuarios autorizados para operar en cuentas.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'Filtrar por ID de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (active, inactive)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Response(
        response: 200,
        description: 'Lista de usuarios autorizados',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                        new OA\Property(property: 'accountId', type: 'string', example: 'a1-...'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                        new OA\Property(property: 'userEmail', type: 'string', example: 'carlos@ejemplo.com'),
                        new OA\Property(property: 'documentType', type: 'string', example: 'CE'),
                        new OA\Property(property: 'documentNumber', type: 'string', example: '123456789'),
                        new OA\Property(property: 'maxAmount', type: 'string', example: '5000.00'),
                        new OA\Property(property: 'dailyLimit', type: 'string', example: '10000.00'),
                        new OA\Property(property: 'monthlyLimit', type: 'string', example: '100000.00'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireScope('authorized.read')]
    #[Route('/authorized', name: 'api_authorized_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [];
        $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($selfServiceUser !== null) {
            // Usuario final (JWT): solo los autorizados de SU propia cuenta.
            $filters['accountId'] = (string) $selfServiceUser->getAccount()?->getId();
        } elseif ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        $filters['limit'] = $request->query->getInt('limit', 50);

        $authorized = $this->authorizedService->listAuthorized($filters);
        $data = array_map(fn($a) => [
            'id' => $a->getId(),
            'accountId' => $a->getAccount()->getId(),
            'accountNumber' => $a->getAccount()->getAccountNumber(),
            'userName' => $a->getUserName(),
            'userEmail' => $a->getUserEmail(),
            'documentType' => $a->getDocumentType(),
            'documentNumber' => $a->getDocumentNumber(),
            'maxAmount' => $a->getMaxAmount(),
            'dailyLimit' => $a->getDailyLimit(),
            'monthlyLimit' => $a->getMonthlyLimit(),
            'status' => $a->getStatus(),
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $authorized);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/authorized',
        summary: 'Crear usuario autorizado',
        description: 'Registra un nuevo usuario autorizado para operar en una cuenta.',
        tags: ['Authorized'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accountId', description: 'ID de la cuenta', type: 'string', example: 'a1b2c3d4-...'),
                new OA\Property(property: 'userName', description: 'Nombre del usuario', type: 'string', example: 'Carlos López'),
                new OA\Property(property: 'userEmail', description: 'Correo electrónico (opcional)', type: 'string', example: 'carlos@ejemplo.com'),
                new OA\Property(property: 'documentType', description: 'Tipo de documento', type: 'string', example: 'CE'),
                new OA\Property(property: 'documentNumber', description: 'Número de documento', type: 'string', example: '123456789'),
                new OA\Property(property: 'maxAmount', description: 'Monto máximo por operación (opcional)', type: 'number', example: 5000.00),
                new OA\Property(property: 'dailyLimit', description: 'Límite diario (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'monthlyLimit', description: 'Límite mensual (opcional)', type: 'number', example: 100000.00),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Usuario autorizado creado exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                    new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 401, description: 'Token OAuth2 inválido o ausente')]
    #[OA\Response(response: 403, description: 'Scope insuficiente (requiere "authorized")')]
    #[RequireScope('authorized.create')]
    #[Route('/authorized', name: 'api_authorized_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $authorized = $this->authorizedService->createAuthorized($dto);
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/authorized/{id}',
        summary: 'Actualizar usuario autorizado',
        description: 'Actualiza los datos de un usuario autorizado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'userName', description: 'Nombre del usuario', type: 'string', example: 'Carlos López'),
                new OA\Property(property: 'userEmail', description: 'Correo electrónico (opcional)', type: 'string', example: 'carlos@ejemplo.com'),
                new OA\Property(property: 'documentType', description: 'Tipo de documento', type: 'string', example: 'CE'),
                new OA\Property(property: 'documentNumber', description: 'Número de documento', type: 'string', example: '123456789'),
                new OA\Property(property: 'maxAmount', description: 'Monto máximo por operación (opcional)', type: 'number', example: 5000.00),
                new OA\Property(property: 'dailyLimit', description: 'Límite diario (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'monthlyLimit', description: 'Límite mensual (opcional)', type: 'number', example: 100000.00),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Authorized user updated')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[RequireScope('authorized.update')]
    #[Route('/authorized/{id}', name: 'api_authorized_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $existing = $this->authorizedService->getAuthorized($id);
            if (!$existing) {
                return $this->error('Authorized user not found', 404);
            }
            if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($existing->getAccount())) {
                return $this->error('Authorized user not found', 404);
            }

            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $authorized = $this->authorizedService->updateAuthorized($id, $dto);
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/authorized/{id}/status',
        summary: 'Cambiar estado de usuario autorizado',
        description: 'Activa o desactiva un usuario autorizado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', description: 'Nuevo estado (active, inactive)', type: 'string', example: 'inactive'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated')]
    #[OA\Response(response: 400, description: 'Error al cambiar estado')]
    #[RequireScope('authorized.status')]
    #[Route('/authorized/{id}/status', name: 'api_authorized_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        try {
            $existing = $this->authorizedService->getAuthorized($id);
            if (!$existing) {
                return $this->error('Authorized user not found', 404);
            }
            if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($existing->getAccount())) {
                return $this->error('Authorized user not found', 404);
            }

            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? 'active';
            $authorized = $this->authorizedService->changeStatus($id, $status);
            return $this->success([
                'id' => $authorized->getId(),
                'status' => $authorized->getStatus(),
            ], 'Status updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/authorized/{doc}/verify',
        summary: 'Verificar usuario autorizado por documento',
        description: 'Busca y verifica un usuario autorizado por su número de documento.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'doc', in: 'path', description: 'Número de documento', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Usuario autorizado encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                    new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Authorized user not found')]
    #[RequireScope('authorized.read')]
    #[Route('/authorized/{doc}/verify', name: 'api_authorized_verify', methods: ['GET'])]
    public function verify(string $doc): JsonResponse
    {
        try {
            $authorized = $this->authorizedService->verifyAuthorized($doc);
            if (!$authorized) {
                return $this->error('Authorized user not found', 404);
            }
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
                'accountNumber' => $authorized->getAccount()->getAccountNumber(),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/authorized/{id}/request-pin',
        summary: 'Solicitar código de verificación',
        description: 'Genera un código de un solo uso y lo envía por WhatsApp al teléfono del autorizado — usarlo luego en POST /authorized/{id}/charge para cobrar.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Código enviado por WhatsApp')]
    #[OA\Response(response: 400, description: 'Autorizado inactivo, sin teléfono configurado, o cooldown activo (esperar 60s entre pedidos)')]
    #[OA\Response(response: 404, description: 'Authorized user not found')]
    #[RequireScope('authorized.request_pin')]
    #[Route('/authorized/{id}/request-pin', name: 'api_authorized_request_pin', methods: ['POST'])]
    public function requestPin(string $id, Request $request): JsonResponse
    {
        $limiter = $this->pinRequestLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->error('Demasiados intentos. Probá de nuevo en un minuto.', 429);
        }

        try {
            $this->authorizedService->requestPin($id);
            return $this->success(null, 'Código enviado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/authorized/{id}/charge',
        summary: 'Consumir saldo de un autorizado (o del titular)',
        description: 'Valida el PIN vigente y ejecuta un cargo contra el cupo del autorizado (o el ' .
            'saldo disponible si es el registro "de sí mismo" del titular, sin cupo reservado). Si ' .
            'se pasa invoiceNumber, paga esa factura pendiente creada antes por el negocio y la deja ' .
            'marcada como pagada; si no, solo registra el movimiento. El PIN es de un solo uso: al ' .
            'concluir con éxito se genera uno nuevo y se notifica por WhatsApp.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['pinCode'],
            properties: [
                new OA\Property(property: 'pinCode', description: 'PIN vigente del autorizado', type: 'string', example: '4821'),
                new OA\Property(property: 'invoiceNumber', description: 'Número de factura a pagar (opcional)', type: 'string', example: 'FAC-2000'),
                new OA\Property(property: 'amount', description: 'Monto a cargar (ignorado si se pasa invoiceNumber)', type: 'number', example: 25.00),
                new OA\Property(property: 'notes', description: 'Descripción libre si no hay factura', type: 'string', example: 'Compra en tienda'),
                new OA\Property(property: 'paymentMethod', description: 'saldo (default, descuenta el saldo de la cuenta) o efectivo (no descuenta saldo, la plata se cobró en persona; igual cuenta contra los límites del autorizado)', type: 'string', example: 'efectivo'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Cargo aplicado')]
    #[OA\Response(response: 400, description: 'PIN inválido, límite excedido, factura no encontrada, etc.')]
    #[RequireScope('authorized.charge')]
    #[Route('/authorized/{id}/charge', name: 'api_authorized_charge', methods: ['POST'])]
    public function charge(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $pinCode = $data['pinCode'] ?? null;
            if (!$pinCode) {
                return $this->error('pinCode is required', 400);
            }

            $result = $this->authorizedService->spend(
                id: $id,
                pinCode: $pinCode,
                invoiceNumber: $data['invoiceNumber'] ?? null,
                amount: isset($data['amount']) ? (string) $data['amount'] : null,
                notes: $data['notes'] ?? null,
                paymentMethod: $data['paymentMethod'] ?? null,
            );

            $authorized = $result['authorized'];
            $invoice = $result['invoice'];

            return $this->success([
                'authorizedId' => $authorized->getId(),
                'usedToday' => $authorized->getUsedToday(),
                'usedThisMonth' => $authorized->getUsedThisMonth(),
                'invoiceNumber' => $invoice?->getInvoiceNumber(),
                'invoiceStatus' => $invoice?->getStatus(),
                'paymentMethod' => $invoice?->getPaymentMethod(),
            ], 'Charge applied');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/authorized/{id}',
        summary: 'Obtener usuario autorizado',
        description: 'Devuelve los detalles de un usuario autorizado por su ID.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Detalle del autorizado')]
    #[OA\Response(response: 404, description: 'Authorized user not found')]
    #[RequireScope('authorized.read')]
    #[Route('/authorized/{id}', name: 'api_authorized_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $authorized = $this->authorizedService->getAuthorized($id);
        if (!$authorized) {
            return $this->error('Authorized user not found', 404);
        }
        if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($authorized->getAccount())) {
            return $this->error('Authorized user not found', 404);
        }

        return $this->success([
            'id' => $authorized->getId(),
            'accountId' => $authorized->getAccount()->getId(),
            'accountNumber' => $authorized->getAccount()->getAccountNumber(),
            'userName' => $authorized->getUserName(),
            'userEmail' => $authorized->getUserEmail(),
            'userPhone' => $authorized->getUserPhone(),
            'documentType' => $authorized->getDocumentType(),
            'documentNumber' => $authorized->getDocumentNumber(),
            'maxAmount' => $authorized->getMaxAmount(),
            'reservedAmount' => $authorized->getReservedAmount(),
            'dailyLimit' => $authorized->getDailyLimit(),
            'monthlyLimit' => $authorized->getMonthlyLimit(),
            'usedToday' => $authorized->getUsedToday(),
            'usedThisMonth' => $authorized->getUsedThisMonth(),
            'status' => $authorized->getStatus(),
            'lastUsedAt' => $authorized->getLastUsedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $authorized->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/authorized/{id}',
        summary: 'Eliminar usuario autorizado',
        description: 'Elimina un usuario autorizado y libera el cupo que le quedara reservado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Authorized user deleted')]
    #[OA\Response(response: 400, description: 'Error al eliminar')]
    #[RequireScope('authorized.delete')]
    #[Route('/authorized/{id}', name: 'api_authorized_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->authorizedService->deleteAuthorized($id);
            return $this->success(null, 'Authorized user deleted');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/authorized/{id}/reset-password',
        summary: 'Restablecer contraseña de un autorizado',
        description: 'Genera un enlace de restablecimiento y lo envía por WhatsApp al autorizado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Enlace de restablecimiento enviado')]
    #[OA\Response(response: 400, description: 'Error al enviar el enlace')]
    #[RequireScope('authorized.reset_password')]
    #[Route('/authorized/{id}/reset-password', name: 'api_authorized_reset_password', methods: ['POST'])]
    public function resetPassword(string $id): JsonResponse
    {
        try {
            $token = bin2hex(random_bytes(32));
            $this->authorizedService->saveResetToken($id, $token);

            $resetUrl = $this->generateUrl('app_reset_password_confirm', ['token' => $token], true);
            $this->authorizedService->resetPassword($id, $resetUrl);

            return $this->success(null, 'Reset link sent via WhatsApp');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

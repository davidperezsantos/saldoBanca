<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\CommissionSettlement;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\CommissionSettlementService;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liquidación de la comisión del sistema entre el Administrador y el Super Administrador, para
 * integraciones externas (app móvil de uso interno, etc.) — sin sesión de panel, así que las 4
 * acciones sensibles (approve/assign-account/settle/close) exigen además un PIN por WhatsApp (ver
 * request-pin/verify-pin) verificado por la MISMA persona que las ejecuta, y que el username
 * indicado en $performedBy tenga el permiso commission_settlements:<acción> en su Rol — el scope
 * OAuth2 certifica que el cliente puede llamar el endpoint, esto certifica que esa persona puntual
 * puede hacer esta acción.
 */
#[OA\Tag(name: 'CommissionSettlements', description: 'Liquidación de la comisión del sistema entre Administrador y Super Administrador')]
class CommissionSettlementController extends BaseController
{
    public function __construct(
        private CommissionSettlementService $settlementService,
        #[Autowire(service: 'limiter.pin_request')]
        private RateLimiterFactory $pinRequestLimiter,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/commission-settlements',
        summary: 'Listar liquidaciones de comisión',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'currency', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[RequireScope('commission_settlements.read')]
    #[Route('/commission-settlements', name: 'api_commission_settlement_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = ['limit' => $request->query->getInt('limit', 50)];
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        if ($request->query->has('currency')) {
            $filters['currency'] = $request->query->get('currency');
        }

        $settlements = $this->settlementService->list($filters);

        return $this->success(array_map(fn(CommissionSettlement $s) => $this->serialize($s), $settlements));
    }

    #[OA\Get(
        path: '/api/v1/commission-settlements/available',
        summary: 'Comisión disponible en una moneda',
        description: 'Convierte al vuelo la comisión ganada en cualquier moneda a la moneda pedida, usando la tasa vigente.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'currency', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[RequireScope('commission_settlements.read')]
    #[Route('/commission-settlements/available', name: 'api_commission_settlement_available', methods: ['GET'])]
    public function available(Request $request): JsonResponse
    {
        $currency = $request->query->get('currency');
        if (!$currency) {
            return $this->error('currency es requerida');
        }

        return $this->success([
            'currency' => strtoupper($currency),
            'available' => $this->settlementService->getAvailableCommission($currency),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/commission-settlements',
        summary: 'Crear liquidación de comisión',
        tags: ['CommissionSettlements'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['amount', 'currency', 'createdBy'],
            properties: [
                new OA\Property(property: 'amount', type: 'number', example: 100.00),
                new OA\Property(property: 'currency', type: 'string', example: 'EUR'),
                new OA\Property(property: 'notes', type: 'string'),
                new OA\Property(property: 'createdBy', description: 'Username del administrador que la crea', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 409, description: 'El monto supera la comisión disponible')]
    #[RequireScope('commission_settlements.create')]
    #[Route('/commission-settlements', name: 'api_commission_settlement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['amount']) || empty($data['currency']) || empty($data['createdBy'])) {
                return $this->error('amount, currency y createdBy son requeridos');
            }

            $this->settlementService->assertUserHasPermission($data['createdBy'], 'create');

            $settlement = $this->settlementService->create(
                (string) $data['amount'],
                (string) $data['currency'],
                $data['notes'] ?? null,
                $data['createdBy']
            );

            return $this->success($this->serialize($settlement), 'Commission settlement created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/commission-settlements/{id}',
        summary: 'Detalle de una liquidación de comisión',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[RequireScope('commission_settlements.read')]
    #[Route('/commission-settlements/{id}', name: 'api_commission_settlement_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $settlement = $this->settlementService->get($id);
        if (!$settlement) {
            return $this->error('Commission settlement not found', 404);
        }

        return $this->success($this->serialize($settlement));
    }

    #[OA\Post(
        path: '/api/v1/commission-settlements/{id}/request-pin',
        summary: 'Solicitar código de verificación',
        description: 'Envía un PIN de un solo uso por WhatsApp al teléfono del usuario indicado — requerido antes de approve/assign-account/settle/close.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['username'], properties: [
        new OA\Property(property: 'username', type: 'string'),
    ]))]
    #[RequireScope('commission_settlements.request_pin')]
    #[Route('/commission-settlements/{id}/request-pin', name: 'api_commission_settlement_request_pin', methods: ['POST'])]
    public function requestPin(string $id, Request $request): JsonResponse
    {
        $limiter = $this->pinRequestLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->error('Demasiados intentos. Probá de nuevo en un minuto.', 429);
        }

        try {
            $data = $this->getJsonContent($request);
            if (empty($data['username'])) {
                return $this->error('username es requerido');
            }

            $this->settlementService->requestPin($id, $data['username']);

            return $this->success(null, 'Código enviado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/commission-settlements/{id}/verify-pin',
        summary: 'Verificar código de verificación',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['username', 'pin'], properties: [
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'pin', type: 'string', example: '482913'),
    ]))]
    #[RequireScope('commission_settlements.request_pin')]
    #[Route('/commission-settlements/{id}/verify-pin', name: 'api_commission_settlement_verify_pin', methods: ['POST'])]
    public function verifyPin(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['username']) || empty($data['pin'])) {
                return $this->error('username y pin son requeridos');
            }

            $this->settlementService->verifyPin($id, $data['username'], (string) $data['pin']);

            return $this->success(null, 'Código verificado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/commission-settlements/{id}/approve',
        summary: 'Aprobar (Administrador)',
        description: 'Requiere PIN verificado por performedBy dentro de los últimos 15 minutos.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['performedBy'], properties: [
        new OA\Property(property: 'performedBy', type: 'string'),
    ]))]
    #[RequireScope('commission_settlements.approve')]
    #[Route('/commission-settlements/{id}/approve', name: 'api_commission_settlement_approve', methods: ['PUT'])]
    public function approve(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['performedBy'])) {
                return $this->error('performedBy es requerido');
            }

            $settlement = $this->getSettlementOrFail($id);
            $this->settlementService->assertUserHasPermission($data['performedBy'], 'approve');
            $this->settlementService->assertPinVerified($settlement, $data['performedBy']);

            $settlement = $this->settlementService->approveByAdmin($id, $data['performedBy']);

            return $this->success($this->serialize($settlement), 'Commission settlement approved');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/commission-settlements/{id}/assign-account',
        summary: 'Asignar cuenta de pago (Super Admin)',
        description: 'Requiere PIN verificado por performedBy dentro de los últimos 15 minutos.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['performedBy', 'payoutAccountNumber'], properties: [
        new OA\Property(property: 'performedBy', type: 'string'),
        new OA\Property(property: 'payoutAccountNumber', type: 'string'),
        new OA\Property(property: 'payoutBankName', type: 'string'),
        new OA\Property(property: 'payoutAccountHolder', type: 'string'),
    ]))]
    #[RequireScope('commission_settlements.assign_account')]
    #[Route('/commission-settlements/{id}/assign-account', name: 'api_commission_settlement_assign_account', methods: ['PUT'])]
    public function assignAccount(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['performedBy'])) {
                return $this->error('performedBy es requerido');
            }

            $settlement = $this->getSettlementOrFail($id);
            $this->settlementService->assertUserHasPermission($data['performedBy'], 'assign_account');
            $this->settlementService->assertPinVerified($settlement, $data['performedBy']);

            $settlement = $this->settlementService->assignPayoutAccount(
                $id,
                $data['payoutAccountNumber'] ?? '',
                $data['payoutBankName'] ?? null,
                $data['payoutAccountHolder'] ?? null,
                $data['performedBy']
            );

            return $this->success($this->serialize($settlement), 'Payout account assigned');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/commission-settlements/{id}/settle',
        summary: 'Liquidar pago (Administrador)',
        description: 'Requiere PIN verificado por performedBy dentro de los últimos 15 minutos.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['performedBy', 'settlementMethod'], properties: [
        new OA\Property(property: 'performedBy', type: 'string'),
        new OA\Property(property: 'settlementMethod', description: 'efectivo o transferencia', type: 'string'),
        new OA\Property(property: 'settlementReference', type: 'string'),
    ]))]
    #[RequireScope('commission_settlements.settle')]
    #[Route('/commission-settlements/{id}/settle', name: 'api_commission_settlement_settle', methods: ['PUT'])]
    public function settle(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['performedBy'])) {
                return $this->error('performedBy es requerido');
            }

            $settlement = $this->getSettlementOrFail($id);
            $this->settlementService->assertUserHasPermission($data['performedBy'], 'settle');
            $this->settlementService->assertPinVerified($settlement, $data['performedBy']);

            $settlement = $this->settlementService->settle(
                $id,
                $data['settlementMethod'] ?? '',
                $data['settlementReference'] ?? null,
                $data['performedBy']
            );

            return $this->success($this->serialize($settlement), 'Commission settlement marked as settled');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/commission-settlements/{id}/close',
        summary: 'Cerrar (Super Admin)',
        description: 'Requiere PIN verificado por performedBy dentro de los últimos 15 minutos.',
        tags: ['CommissionSettlements'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['performedBy'], properties: [
        new OA\Property(property: 'performedBy', type: 'string'),
    ]))]
    #[RequireScope('commission_settlements.close')]
    #[Route('/commission-settlements/{id}/close', name: 'api_commission_settlement_close', methods: ['PUT'])]
    public function close(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            if (empty($data['performedBy'])) {
                return $this->error('performedBy es requerido');
            }

            $settlement = $this->getSettlementOrFail($id);
            $this->settlementService->assertUserHasPermission($data['performedBy'], 'close');
            $this->settlementService->assertPinVerified($settlement, $data['performedBy']);

            $settlement = $this->settlementService->close($id, $data['performedBy']);

            return $this->success($this->serialize($settlement), 'Commission settlement closed');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getSettlementOrFail(string $id): CommissionSettlement
    {
        $settlement = $this->settlementService->get($id);
        if (!$settlement) {
            throw new \App\Exception\NotFoundException('Commission settlement not found');
        }

        return $settlement;
    }

    private function serialize(CommissionSettlement $s): array
    {
        return [
            'id' => $s->getId(),
            'settlementNumber' => $s->getSettlementNumber(),
            'amount' => $s->getAmount(),
            'currency' => $s->getCurrency(),
            'status' => $s->getStatus(),
            'createdBy' => $s->getCreatedBy(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            'notes' => $s->getNotes(),
            'adminApprovedAt' => $s->getAdminApprovedAt()?->format('Y-m-d H:i:s'),
            'adminApprovedBy' => $s->getAdminApprovedBy(),
            'payoutAccountNumber' => $s->getPayoutAccountNumber(),
            'payoutBankName' => $s->getPayoutBankName(),
            'payoutAccountHolder' => $s->getPayoutAccountHolder(),
            'accountAssignedAt' => $s->getAccountAssignedAt()?->format('Y-m-d H:i:s'),
            'accountAssignedBy' => $s->getAccountAssignedBy(),
            'settlementMethod' => $s->getSettlementMethod(),
            'settlementReference' => $s->getSettlementReference(),
            'settledAt' => $s->getSettledAt()?->format('Y-m-d H:i:s'),
            'settledBy' => $s->getSettledBy(),
            'closedAt' => $s->getClosedAt()?->format('Y-m-d H:i:s'),
            'closedBy' => $s->getClosedBy(),
        ];
    }
}

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\RechargeService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Recharges', description: 'Recargas y depósitos')]
class RechargeController extends BaseController
{
    public function __construct(
        private RechargeService $rechargeService,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/recharges',
        summary: 'Listar recargas',
        description: 'Obtiene el listado de recargas con filtros opcionales.',
        tags: ['Recharges'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'Filtrar por ID de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (pending, completed, cancelled, failed)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'rechargeType', in: 'query', description: 'Filtrar por tipo (manual, automatic, external)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Desplazamiento', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Lista de recargas',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'r1-...'),
                        new OA\Property(property: 'accountId', type: 'string', example: 'a1-...'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'amount', type: 'string', example: '500.00'),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'rechargeType', type: 'string', example: 'manual'),
                        new OA\Property(property: 'referenceNumber', type: 'string', example: 'REF-001'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                        new OA\Property(property: 'paymentMethod', type: 'string', example: 'bank_transfer'),
                        new OA\Property(property: 'notes', type: 'string', example: 'Recarga mensual'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[RequireScope('recharges.read')]
    #[Route('/recharges', name: 'api_recharge_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($selfServiceUser !== null) {
            // Usuario final (JWT): solo sus propias recargas, ignorando cualquier accountId que
            // venga en la query — mismo criterio que AccountController::list().
            $filters['accountId'] = (string) $selfServiceUser->getAccount()?->getId();
        } elseif ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        if ($request->query->has('rechargeType')) {
            $filters['rechargeType'] = $request->query->get('rechargeType');
        }

        $recharges = $this->rechargeService->listRecharges($filters);
        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'receiptNumber' => $r->getReceiptNumber(),
            'accountId' => $r->getAccount()->getId(),
            'accountNumber' => $r->getAccount()->getAccountNumber(),
            'amount' => $r->getAmount(),
            'currency' => $r->getCurrency(),
            'rechargeType' => $r->getRechargeType(),
            'referenceNumber' => $r->getReferenceNumber(),
            'status' => $r->getStatus(),
            'paymentMethod' => $r->getPaymentMethod(),
            'notes' => $r->getNotes(),
            'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $recharges);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/recharges',
        summary: 'Crear recarga',
        description: 'Registra una nueva recarga. Requiere API Key.',
        tags: ['Recharges'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accountId', description: 'ID de la cuenta', type: 'string', example: 'a1b2c3d4-...'),
                new OA\Property(property: 'amount', description: 'Monto de la recarga', type: 'number', example: 500.00),
                new OA\Property(property: 'currency', description: 'Código de moneda', type: 'string', example: 'USD'),
                new OA\Property(property: 'rechargeType', description: 'Tipo de recarga', type: 'string', example: 'manual'),
                new OA\Property(property: 'paymentMethod', description: 'Método de pago', type: 'string', example: 'bank_transfer'),
                new OA\Property(property: 'referenceNumber', description: 'Número de referencia (opcional)', type: 'string', example: 'REF-001'),
                new OA\Property(property: 'notes', description: 'Notas adicionales (opcional)', type: 'string', example: 'Recarga mensual'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Recarga creada exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Recharge created'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'r1-...'),
                    new OA\Property(property: 'amount', type: 'string', example: '500.00'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 401, description: 'Token OAuth2 inválido o ausente')]
    #[OA\Response(response: 403, description: 'Scope insuficiente (requiere "recharges")')]
    #[RequireScope('recharges.create')]
    #[Route('/recharges', name: 'api_recharge_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $recharge = $this->rechargeService->processExternalRecharge($data);
            return $this->success([
                'id' => $recharge->getId(),
                'receiptNumber' => $recharge->getReceiptNumber(),
                'amount' => $recharge->getAmount(),
                'status' => $recharge->getStatus(),
            ], 'Recharge created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Get(
        path: '/api/v1/recharges/{id}',
        summary: 'Obtener recarga',
        description: 'Devuelve los detalles de una recarga por su ID.',
        tags: ['Recharges'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la recarga', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Detalle de la recarga',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'r1-...'),
                    new OA\Property(property: 'accountId', type: 'string', example: 'a1-...'),
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'amount', type: 'string', example: '500.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                    new OA\Property(property: 'rechargeType', type: 'string', example: 'manual'),
                    new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    new OA\Property(property: 'paymentMethod', type: 'string', example: 'bank_transfer'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Recarga mensual'),
                    new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Recharge not found')]
    #[RequireScope('recharges.read')]
    #[Route('/recharges/{id}', name: 'api_recharge_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $recharge = $this->rechargeService->getRecharge($id);
            if (!$recharge) {
                return $this->error('Recharge not found', 404);
            }
            if (!$this->scopeAuthorizationService->selfServiceOwnsAccount($recharge->getAccount())) {
                return $this->error('Recharge not found', 404);
            }
            return $this->success([
                'id' => $recharge->getId(),
                'receiptNumber' => $recharge->getReceiptNumber(),
                'accountId' => $recharge->getAccount()->getId(),
                'accountNumber' => $recharge->getAccount()->getAccountNumber(),
                'amount' => $recharge->getAmount(),
                'currency' => $recharge->getCurrency(),
                'rechargeType' => $recharge->getRechargeType(),
                'referenceNumber' => $recharge->getReferenceNumber(),
                'status' => $recharge->getStatus(),
                'paymentMethod' => $recharge->getPaymentMethod(),
                'notes' => $recharge->getNotes(),
                'createdAt' => $recharge->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/recharges/{id}/complete',
        summary: 'Completar recarga',
        description: 'Marca una recarga como completada y acredita el saldo en la cuenta.',
        tags: ['Recharges'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la recarga', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Recharge completed')]
    #[OA\Response(response: 400, description: 'Error al completar recarga')]
    #[RequireScope('recharges.complete')]
    #[Route('/recharges/{id}/complete', name: 'api_recharge_complete', methods: ['PUT'])]
    public function complete(string $id): JsonResponse
    {
        try {
            $recharge = $this->rechargeService->completeRecharge($id);
            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge completed');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/recharges/{id}/cancel',
        summary: 'Cancelar recarga',
        description: 'Cancela una recarga pendiente.',
        tags: ['Recharges'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la recarga', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Recharge cancelled')]
    #[OA\Response(response: 400, description: 'Error al cancelar recarga')]
    #[RequireScope('recharges.cancel')]
    #[Route('/recharges/{id}/cancel', name: 'api_recharge_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        try {
            $recharge = $this->rechargeService->cancelRecharge($id);
            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge cancelled');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/recharges/{id}/fail',
        summary: 'Marcar recarga como fallida',
        description: 'Marca una recarga como fallida con un motivo.',
        tags: ['Recharges'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la recarga', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'reason', description: 'Motivo del fallo', type: 'string', example: 'Fondos insuficientes'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Recharge marked as failed')]
    #[OA\Response(response: 400, description: 'Error al marcar recarga como fallida')]
    #[RequireScope('recharges.fail')]
    #[Route('/recharges/{id}/fail', name: 'api_recharge_fail', methods: ['PUT'])]
    public function fail(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $reason = $data['reason'] ?? 'Failed';
            $recharge = $this->rechargeService->failRecharge($id, $reason);
            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge marked as failed');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

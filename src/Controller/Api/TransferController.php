<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\TransferDto;
use App\Services\Balance\TransferService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Transfers', description: 'Transferencias entre cuentas')]
class TransferController extends BaseController
{
    public function __construct(
        private TransferService $transferService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/transfers',
        summary: 'Listar transferencias',
        description: 'Obtiene el listado de transferencias con filtros opcionales.',
        tags: ['Transfers'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'Filtrar por ID de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (pending, processed, cancelled)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Desplazamiento', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Lista de transferencias',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 't1-...'),
                        new OA\Property(property: 'originAccountId', type: 'string', example: 'a1-...'),
                        new OA\Property(property: 'originAccountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'destinationAccountId', type: 'string', example: 'a2-...'),
                        new OA\Property(property: 'destAccountNumber', type: 'string', example: '987-654-321'),
                        new OA\Property(property: 'amount', type: 'string', example: '1000.00'),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'fee', type: 'string', example: '5.00'),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'notes', type: 'string', example: 'Pago proveedor'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[Route('/transfers', name: 'api_transfer_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        if ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        $transfers = $this->transferService->listTransfers($filters);
        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'originAccountId' => $t->getOriginAccount()->getId(),
            'originAccountNumber' => $t->getOriginAccountNumber(),
            'destinationAccountId' => $t->getDestinationAccount()->getId(),
            'destAccountNumber' => $t->getDestAccountNumber(),
            'amount' => $t->getAmount(),
            'currency' => $t->getCurrency(),
            'fee' => $t->getFee(),
            'status' => $t->getStatus(),
            'notes' => $t->getNotes(),
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $transfers);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/transfers',
        summary: 'Crear transferencia',
        description: 'Registra una nueva transferencia entre cuentas. Requiere API Key.',
        tags: ['Transfers'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'originAccountNumber', description: 'Número de cuenta origen', type: 'string', example: '123-456-789'),
                new OA\Property(property: 'destAccountNumber', description: 'Número de cuenta destino', type: 'string', example: '987-654-321'),
                new OA\Property(property: 'amount', description: 'Monto a transferir', type: 'number', example: 1000.00),
                new OA\Property(property: 'currency', description: 'Código de moneda', type: 'string', example: 'USD'),
                new OA\Property(property: 'notes', description: 'Notas (opcional)', type: 'string', example: 'Pago proveedor'),
                new OA\Property(property: 'authorizedBy', description: 'Autorizado por (opcional)', type: 'string', example: 'admin'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Transferencia creada exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Transfer created'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 't1-...'),
                    new OA\Property(property: 'amount', type: 'string', example: '1000.00'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    new OA\Property(property: 'originAccountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'destAccountNumber', type: 'string', example: '987-654-321'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 401, description: 'Invalid API key')]
    #[Route('/transfers', name: 'api_transfer_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->checkApiKey($request)) {
            return $this->error('Invalid API key', 401);
        }
        try {
            $data = $this->getJsonContent($request);
            $dto = TransferDto::fromJson($data);
            $transfer = $this->transferService->createTransfer($dto);
            return $this->success([
                'id' => $transfer->getId(),
                'amount' => $transfer->getAmount(),
                'status' => $transfer->getStatus(),
                'originAccountNumber' => $transfer->getOriginAccountNumber(),
                'destAccountNumber' => $transfer->getDestAccountNumber(),
            ], 'Transfer created', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/transfers/{id}',
        summary: 'Obtener transferencia',
        description: 'Devuelve los detalles de una transferencia por su ID.',
        tags: ['Transfers'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la transferencia', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Detalle de la transferencia',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 't1-...'),
                    new OA\Property(property: 'originAccountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'destAccountNumber', type: 'string', example: '987-654-321'),
                    new OA\Property(property: 'amount', type: 'string', example: '1000.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                    new OA\Property(property: 'fee', type: 'string', example: '5.00'),
                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                    new OA\Property(property: 'notes', type: 'string', example: 'Pago proveedor'),
                    new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Transfer not found')]
    #[Route('/transfers/{id}', name: 'api_transfer_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->getTransfer($id);
            if (!$transfer) {
                return $this->error('Transfer not found', 404);
            }
            return $this->success([
                'id' => $transfer->getId(),
                'originAccountId' => $transfer->getOriginAccount()->getId(),
                'originAccountNumber' => $transfer->getOriginAccountNumber(),
                'destinationAccountId' => $transfer->getDestinationAccount()->getId(),
                'destAccountNumber' => $transfer->getDestAccountNumber(),
                'amount' => $transfer->getAmount(),
                'currency' => $transfer->getCurrency(),
                'fee' => $transfer->getFee(),
                'status' => $transfer->getStatus(),
                'authorizedBy' => $transfer->getAuthorizedBy(),
                'notes' => $transfer->getNotes(),
                'createdAt' => $transfer->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(
        path: '/api/v1/transfers/{id}/process',
        summary: 'Procesar transferencia',
        description: 'Ejecuta una transferencia pendiente, debitando de origen y acreditando a destino.',
        tags: ['Transfers'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la transferencia', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Transfer processed')]
    #[OA\Response(response: 400, description: 'Error al procesar transferencia')]
    #[Route('/transfers/{id}/process', name: 'api_transfer_process', methods: ['PUT'])]
    public function process(string $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->processTransfer($id);
            return $this->success([
                'id' => $transfer->getId(),
                'status' => $transfer->getStatus(),
            ], 'Transfer processed');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(
        path: '/api/v1/transfers/{id}/cancel',
        summary: 'Cancelar transferencia',
        description: 'Cancela una transferencia pendiente.',
        tags: ['Transfers'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la transferencia', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Transfer cancelled')]
    #[OA\Response(response: 400, description: 'Error al cancelar transferencia')]
    #[Route('/transfers/{id}/cancel', name: 'api_transfer_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->cancelTransfer($id);
            return $this->success([
                'id' => $transfer->getId(),
                'status' => $transfer->getStatus(),
            ], 'Transfer cancelled');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/transfers/limits/{accountId}',
        summary: 'Límites de transferencia',
        description: 'Obtiene los límites de transferencia para una cuenta (montos máximos, diarios, mensuales).',
        tags: ['Transfers'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Límites de transferencia',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'maxPerTransfer', type: 'string', example: '10000.00'),
                    new OA\Property(property: 'maxDaily', type: 'string', example: '50000.00'),
                    new OA\Property(property: 'maxMonthly', type: 'string', example: '500000.00'),
                ], type: 'object'),
            ]
        )
    )]
    #[Route('/transfers/limits/{accountId}', name: 'api_transfer_limits', methods: ['GET'])]
    public function limits(string $accountId): JsonResponse
    {
        try {
            $limits = $this->transferService->getTransferLimits($accountId);
            return $this->success($limits);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

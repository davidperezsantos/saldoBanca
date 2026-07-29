<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Security\Attribute\RequireAnyScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\BalanceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'History', description: 'Historial de movimientos')]
class HistoryController extends BaseController
{
    public function __construct(
        private BalanceService $balanceService,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/history',
        summary: 'Historial de movimientos',
        description: 'Obtiene los movimientos de una cuenta con filtros opcionales por tipo y rango de fechas.',
        tags: ['History'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'ID de la cuenta (requerido)', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'movementType', in: 'query', description: 'Filtrar por tipo de movimiento (credit, debit, etc.)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'dateFrom', in: 'query', description: 'Fecha inicial (Y-m-d H:i:s)', schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'dateTo', in: 'query', description: 'Fecha final (Y-m-d H:i:s)', schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Desplazamiento', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(
        response: 200,
        description: 'Lista de movimientos',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'm1-...'),
                        new OA\Property(property: 'movementType', type: 'string', example: 'credit'),
                        new OA\Property(property: 'amount', type: 'string', example: '500.00'),
                        new OA\Property(property: 'balanceBefore', type: 'string', example: '1000.00'),
                        new OA\Property(property: 'balanceAfter', type: 'string', example: '1500.00'),
                        new OA\Property(property: 'referenceType', type: 'string', example: 'recharge'),
                        new OA\Property(property: 'referenceId', type: 'string', example: 'r1-...'),
                        new OA\Property(property: 'description', type: 'string', example: 'Recarga manual'),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'performedBy', type: 'string', example: 'admin'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'accountId is required')]
    #[RequireAnyScope('history.read', 'history_admin.read')]
    #[Route('/history', name: 'api_history_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $accountId = $request->query->get('accountId');
            if (!$accountId) {
                return $this->error('accountId is required', 400);
            }
            $isAdmin = $this->scopeAuthorizationService->hasScope('history_admin.read');
            $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
            if (!$isAdmin && $selfServiceUser !== null && (string) $selfServiceUser->getAccount()?->getId() !== $accountId) {
                return $this->error('No podés consultar el historial de esta cuenta', 403);
            }
            $filters = [];
            if ($request->query->has('movementType')) {
                $filters['movementType'] = $request->query->get('movementType');
            }
            if ($request->query->has('dateFrom')) {
                $filters['dateFrom'] = $request->query->get('dateFrom');
            }
            if ($request->query->has('dateTo')) {
                $filters['dateTo'] = $request->query->get('dateTo');
            }
            $filters['limit'] = $request->query->getInt('limit', 50);
            $filters['offset'] = $request->query->getInt('offset', 0);

            $movements = $this->balanceService->getMovements($accountId, $filters);
            $data = array_map(fn($m) => [
                'id' => $m->getId(),
                'movementType' => $m->getMovementType(),
                'amount' => $m->getAmount(),
                'balanceBefore' => $m->getBalanceBefore(),
                'balanceAfter' => $m->getBalanceAfter(),
                'referenceType' => $m->getReferenceType(),
                'referenceId' => $m->getReferenceId(),
                'description' => $m->getDescription(),
                'currency' => $m->getCurrency(),
                'performedBy' => $m->getPerformedBy(),
                'createdAt' => $m->getCreatedAt()?->format('Y-m-d H:i:s'),
            ], $movements);

            return $this->success($data);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

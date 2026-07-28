<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\BalanceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Historial de movimientos de CUALQUIER cuenta del sistema desde mobile/ — espejo de
 * Controller/Api/HistoryController (self-service) sin el chequeo de ownership: acá el caller es
 * staff, nunca dueño de la cuenta que consulta.
 */
#[OA\Tag(name: 'Admin History', description: 'Historial de movimientos de cualquier cuenta (requiere scope history_admin.read)')]
class HistoryController extends BaseController
{
    public function __construct(
        private BalanceService $balanceService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/history',
        summary: 'Historial de movimientos de cualquier cuenta',
        tags: ['Admin History'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'movementType', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'dateFrom', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'dateTo', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(response: 200, description: 'Lista de movimientos')]
    #[OA\Response(response: 400, description: 'accountId is required')]
    #[RequireScope('history_admin.read')]
    #[Route('/admin/history', name: 'api_admin_history_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $accountId = $request->query->get('accountId');
            if (!$accountId) {
                return $this->error('accountId is required', 400);
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

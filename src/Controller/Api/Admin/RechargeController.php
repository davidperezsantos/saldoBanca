<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\RechargeService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recargas de TODAS las cuentas del sistema desde mobile/ — espejo de Controller/Api/RechargeController
 * (self-service) sin la restricción a "mi propia cuenta": acá el caller es staff.
 */
#[OA\Tag(name: 'Admin Recharges', description: 'Recargas del sistema (requiere scope recharges_admin.read)')]
class RechargeController extends BaseController
{
    public function __construct(
        private RechargeService $rechargeService,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/recharges', summary: 'Listar recargas del sistema', tags: ['Admin Recharges'])]
    #[OA\Parameter(name: 'accountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'rechargeType', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(response: 200, description: 'Lista de recargas')]
    #[RequireScope('recharges_admin.read')]
    #[Route('/admin/recharges', name: 'api_admin_recharge_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        foreach (['accountId', 'status', 'rechargeType'] as $key) {
            if ($request->query->has($key)) {
                $filters[$key] = $request->query->get($key);
            }
        }

        $recharges = $this->rechargeService->listRecharges($filters);
        $data = array_map(fn($r) => [
            'id' => $r->getId(),
            'receiptNumber' => $r->getReceiptNumber(),
            'accountId' => $r->getAccount()->getId(),
            'accountNumber' => $r->getAccount()->getAccountNumber(),
            'accountName' => $r->getAccount()->getBusinessName(),
            'amount' => $r->getAmount(),
            'currency' => $r->getCurrency(),
            'originalAmount' => $r->getOriginalAmount(),
            'originalCurrency' => $r->getOriginalCurrency(),
            'exchangeRate' => $r->getExchangeRate(),
            'rechargeType' => $r->getRechargeType(),
            'referenceNumber' => $r->getReferenceNumber(),
            'status' => $r->getStatus(),
            'paymentMethod' => $r->getPaymentMethod(),
            'notes' => $r->getNotes(),
            'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $recharges);

        return $this->success($data);
    }
}

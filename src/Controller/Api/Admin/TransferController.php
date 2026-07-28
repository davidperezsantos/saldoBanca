<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\TransferService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Transferencias de TODAS las cuentas del sistema desde mobile/ — espejo de Controller/Api/TransferController
 * (self-service) sin la restricción a "mi propia cuenta": acá el caller es staff.
 */
#[OA\Tag(name: 'Admin Transfers', description: 'Transferencias del sistema (requiere scope transfers_admin.read)')]
class TransferController extends BaseController
{
    public function __construct(
        private TransferService $transferService,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/transfers', summary: 'Listar transferencias del sistema', tags: ['Admin Transfers'])]
    #[OA\Parameter(name: 'accountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(response: 200, description: 'Lista de transferencias')]
    #[RequireScope('transfers_admin.read')]
    #[Route('/admin/transfers', name: 'api_admin_transfer_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        foreach (['accountId', 'status'] as $key) {
            if ($request->query->has($key)) {
                $filters[$key] = $request->query->get($key);
            }
        }

        $transfers = $this->transferService->listTransfers($filters);
        $data = array_map(fn($t) => [
            'id' => $t->getId(),
            'receiptNumber' => $t->getReceiptNumber(),
            'originAccountId' => $t->getOriginAccount()->getId(),
            'originAccountNumber' => $t->getOriginAccountNumber(),
            'originAccountName' => $t->getOriginAccount()->getBusinessName(),
            'destinationAccountId' => $t->getDestinationAccount()->getId(),
            'destAccountNumber' => $t->getDestAccountNumber(),
            'destAccountName' => $t->getDestinationAccount()->getBusinessName(),
            'amount' => $t->getAmount(),
            'currency' => $t->getCurrency(),
            'originalAmount' => $t->getOriginalAmount(),
            'originalCurrency' => $t->getOriginalCurrency(),
            'fee' => $t->getFee(),
            'status' => $t->getStatus(),
            'notes' => $t->getNotes(),
            'createdAt' => $t->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $transfers);

        return $this->success($data);
    }
}

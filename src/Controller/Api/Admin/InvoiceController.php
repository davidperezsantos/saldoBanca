<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\InvoiceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Facturas de TODAS las cuentas del sistema desde mobile/ — espejo de Controller/Api/InvoiceController
 * (self-service) sin la restricción a "mi propia cuenta": acá el caller es staff.
 */
#[OA\Tag(name: 'Admin Invoices', description: 'Facturas del sistema (requiere scope invoices_admin.read)')]
class InvoiceController extends BaseController
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/invoices', summary: 'Listar facturas del sistema', tags: ['Admin Invoices'])]
    #[OA\Parameter(name: 'accountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20))]
    #[OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\Response(response: 200, description: 'Lista de facturas')]
    #[RequireScope('invoices_admin.read')]
    #[Route('/admin/invoices', name: 'api_admin_invoice_list', methods: ['GET'])]
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

        $invoices = $this->invoiceService->listInvoices($filters);
        $data = array_map(fn($i) => $this->invoiceService->serializeForDisplay($i), $invoices);

        return $this->success($data);
    }
}

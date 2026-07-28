<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\BusinessReconciliationService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reporte de conciliaciones de negocio desde mobile/ — espejo JSON del endpoint `data()` de
 * Controller/Admin/ReconciliationReportController (panel Twig). No incluye `page()` ni `pdf()`:
 * la exportación a PDF se queda en el panel por ahora.
 */
#[OA\Tag(name: 'Admin Reconciliation Report', description: 'Reporte de conciliaciones (requiere scope reconciliations_admin.read)')]
class ReconciliationReportController extends BaseController
{
    public function __construct(
        private BusinessReconciliationService $reconciliationService,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/reports/reconciliations', summary: 'Reporte de conciliaciones', tags: ['Admin Reconciliation Report'])]
    #[OA\Parameter(name: 'periodStart', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'periodEnd', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'businessAccountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[RequireScope('reconciliations_admin.read')]
    #[Route('/admin/reports/reconciliations', name: 'api_admin_reconciliation_report_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $periodStart = $request->query->get('periodStart');
        $periodEnd = $request->query->get('periodEnd');
        if (!$periodStart || !$periodEnd) {
            return $this->error('periodStart y periodEnd son requeridos');
        }

        try {
            $report = $this->reconciliationService->buildReconciliationReport(
                new \DateTimeImmutable($periodStart),
                new \DateTimeImmutable($periodEnd . ' 23:59:59'),
                $request->query->get('status') ?: null,
                $request->query->get('businessAccountId') ?: null
            );

            return $this->success($report);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

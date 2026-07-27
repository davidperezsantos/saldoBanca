<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Services\Balance\BusinessReconciliationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports/reconciliations')]
class ReconciliationReportController extends BaseController
{
    public function __construct(
        private BusinessReconciliationService $reconciliationService,
    ) {
    }

    #[Route('', name: 'admin_reconciliation_report_page', methods: ['GET'])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted('reconciliations:view');

        return $this->render('admin/reconciliation_report.html.twig');
    }

    #[Route('/data', name: 'admin_reconciliation_report_data', methods: ['GET'])]
    public function data(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:view');

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

    #[Route('/pdf', name: 'admin_reconciliation_report_pdf', methods: ['GET'])]
    public function pdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted('reconciliations:view');

        $periodStart = $request->query->get('periodStart');
        $periodEnd = $request->query->get('periodEnd');
        if (!$periodStart || !$periodEnd) {
            return $this->error('periodStart y periodEnd son requeridos');
        }

        $report = $this->reconciliationService->buildReconciliationReport(
            new \DateTimeImmutable($periodStart),
            new \DateTimeImmutable($periodEnd . ' 23:59:59'),
            $request->query->get('status') ?: null,
            $request->query->get('businessAccountId') ?: null
        );

        $html = $this->renderView('reports/reconciliation_report_pdf.html.twig', ['report' => $report]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                'attachment; filename="conciliaciones_%s_%s.pdf"',
                $report['periodStart'],
                $report['periodEnd']
            ),
        ]);
    }
}

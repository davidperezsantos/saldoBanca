<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\BankReconciliation;
use App\Services\Balance\ReconciliationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reconciliation')]
class ReconciliationController extends BaseController
{
    public function __construct(
        private ReconciliationService $reconciliationService,
    ) {
    }

    /**
     * Ejecuta una conciliación: compara lo que el sistema acreditó para $gatewayCode contra la
     * lista de movimientos que el externo reporta (body: transactions: [{referenceNumber, amount}]).
     * periodStart/periodEnd (opcionales, "Y-m-d") acotan la ventana de recargas propias a revisar
     * para detectar "missing_external" (default: últimos 30 días).
     */
    #[Route('/{gatewayCode}', name: 'admin_reconciliation_run', methods: ['POST'])]
    public function run(string $gatewayCode, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliation:run');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $transactions = $data['transactions'] ?? [];

            $periodStart = isset($data['periodStart']) ? new \DateTimeImmutable($data['periodStart']) : null;
            $periodEnd = isset($data['periodEnd']) ? new \DateTimeImmutable($data['periodEnd'] . ' 23:59:59') : null;

            $run = $this->reconciliationService->reconcile(
                gatewayCode: $gatewayCode,
                externalTransactions: $transactions,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
                performedBy: $this->getUser()?->getUserIdentifier(),
            );

            return $this->success($this->serialize($run), 'Reconciliation completed');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('', name: 'admin_reconciliation_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliation:view');

        $filters = ['limit' => $request->query->getInt('limit', 50)];
        if ($request->query->has('gatewayCode')) {
            $filters['gatewayCode'] = $request->query->get('gatewayCode');
        }

        $runs = $this->reconciliationService->listRuns($filters);

        return $this->success(array_map(fn(BankReconciliation $r) => $this->serialize($r), $runs));
    }

    #[Route('/{id}', name: 'admin_reconciliation_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliation:view');

        $run = $this->reconciliationService->getRun($id);
        if (!$run) {
            return $this->error('Reconciliation run not found', 404);
        }

        return $this->success($this->serialize($run, includeDiscrepancies: true));
    }

    private function serialize(BankReconciliation $run, bool $includeDiscrepancies = false): array
    {
        $data = [
            'id' => $run->getId(),
            'gatewayCode' => $run->getGatewayCode(),
            'periodStart' => $run->getPeriodStart()?->format('Y-m-d H:i:s'),
            'periodEnd' => $run->getPeriodEnd()?->format('Y-m-d H:i:s'),
            'totalExternal' => $run->getTotalExternal(),
            'totalMatched' => $run->getTotalMatched(),
            'totalMismatched' => $run->getTotalMismatched(),
            'totalMissingInternal' => $run->getTotalMissingInternal(),
            'totalMissingExternal' => $run->getTotalMissingExternal(),
            'performedBy' => $run->getPerformedBy(),
            'createdAt' => $run->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        if ($includeDiscrepancies) {
            $data['discrepancies'] = $run->getDiscrepancies();
        }

        return $data;
    }
}

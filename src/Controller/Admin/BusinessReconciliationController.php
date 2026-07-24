<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\BusinessReconciliation;
use App\Entity\Balance\BusinessReconciliationEvent;
use App\Entity\Balance\InvoicePayment;
use App\Services\Balance\BusinessReconciliationService;
use App\Services\Balance\InvoiceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reconciliations')]
class BusinessReconciliationController extends BaseController
{
    public function __construct(
        private BusinessReconciliationService $reconciliationService,
        private InvoiceService $invoiceService,
    ) {
    }

    #[Route('', name: 'admin_reconciliations_page', methods: ['GET'])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted('reconciliations:view');

        return $this->render('admin/reconciliations.html.twig');
    }

    #[Route('/preview', name: 'admin_reconciliation_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:create');

        try {
            $businessAccountId = $request->query->get('businessAccountId');
            $periodStart = $request->query->get('periodStart');
            $periodEnd = $request->query->get('periodEnd');

            if (!$businessAccountId || !$periodStart || !$periodEnd) {
                return $this->error('businessAccountId, periodStart y periodEnd son requeridos');
            }

            $result = $this->reconciliationService->preview(
                $businessAccountId,
                new \DateTimeImmutable($periodStart),
                new \DateTimeImmutable($periodEnd . ' 23:59:59')
            );

            return $this->success([
                'invoices' => array_map(fn(InvoicePayment $i) => $this->invoiceService->serializeForDisplay($i), $result['invoices']),
                'total' => $result['total'],
                'currency' => $result['currency'],
                'taxPercent' => $result['taxPercent'],
                'taxAmount' => $result['taxAmount'],
                'netAmount' => $result['netAmount'],
                'payoutSplitPreview' => $result['payoutSplitPreview'],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('', name: 'admin_reconciliation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:create');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $businessAccountId = $data['businessAccountId'] ?? null;
            $periodStart = $data['periodStart'] ?? null;
            $periodEnd = $data['periodEnd'] ?? null;

            if (!$businessAccountId || !$periodStart || !$periodEnd) {
                return $this->error('businessAccountId, periodStart y periodEnd son requeridos');
            }

            $reconciliation = $this->reconciliationService->create(
                $businessAccountId,
                new \DateTimeImmutable($periodStart),
                new \DateTimeImmutable($periodEnd . ' 23:59:59'),
                $this->getUser()?->getUserIdentifier()
            );

            return $this->success($this->serialize($reconciliation), 'Reconciliation created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/list', name: 'admin_reconciliation_index', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:view');

        $filters = ['limit' => $request->query->getInt('limit', 50)];
        if ($request->query->has('businessAccountId')) {
            $filters['businessAccountId'] = $request->query->get('businessAccountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        $reconciliations = $this->reconciliationService->list($filters);

        return $this->success(array_map(fn(BusinessReconciliation $r) => $this->serialize($r), $reconciliations));
    }

    #[Route('/{id}', name: 'admin_business_reconciliation_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:details');

        $reconciliation = $this->reconciliationService->get($id);
        if (!$reconciliation) {
            return $this->error('Reconciliation not found', 404);
        }

        $this->reconciliationService->backfillPayoutSplitIfMissing($reconciliation);

        $data = $this->serialize($reconciliation);
        $data['invoices'] = array_map(
            fn(InvoicePayment $i) => $this->invoiceService->serializeForDisplay($i),
            $reconciliation->getInvoices()->toArray()
        );
        $data['events'] = array_map(
            fn(BusinessReconciliationEvent $e) => $this->serializeEvent($e),
            $this->reconciliationService->getEvents($id)
        );

        return $this->success($data);
    }

    #[Route('/{id}/send', name: 'admin_reconciliation_send', methods: ['POST'])]
    public function send(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:send');

        try {
            $this->validateCsrfToken();
            $reconciliation = $this->reconciliationService->send($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($reconciliation), 'Reconciliation sent');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/approve', name: 'admin_reconciliation_approve', methods: ['PUT'])]
    public function approve(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:approve');

        try {
            $this->validateCsrfToken();
            $reconciliation = $this->reconciliationService->approveByAdmin($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($reconciliation), 'Reconciliation approved');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/reject', name: 'admin_reconciliation_reject', methods: ['PUT'])]
    public function reject(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:approve');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $reconciliation = $this->reconciliationService->rejectByAdmin(
                $id,
                $this->getUser()?->getUserIdentifier(),
                $data['reason'] ?? null
            );
            return $this->success($this->serialize($reconciliation), 'Reconciliation rejected');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/settle', name: 'admin_reconciliation_settle', methods: ['PUT'])]
    public function settle(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('reconciliations:settle');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $reconciliation = $this->reconciliationService->settle(
                $id,
                $data['settlementMethod'] ?? '',
                $data['settlementReference'] ?? null,
                $data['settlementSecondaryMethod'] ?? null,
                $data['settlementSecondaryReference'] ?? null,
                $data['settlementNotes'] ?? null,
                $this->getUser()?->getUserIdentifier()
            );
            return $this->success($this->serialize($reconciliation), 'Reconciliation settled');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function serialize(BusinessReconciliation $r): array
    {
        return [
            'id' => $r->getId(),
            'reconciliationNumber' => $r->getReconciliationNumber(),
            'businessAccountId' => $r->getBusinessAccount()->getId(),
            'businessAccountName' => $r->getBusinessAccount()->getBusinessName(),
            'payoutAccountBase' => $this->serializePayoutAccount($r->getPayoutAccountBase()),
            'payoutAccountSecondary' => $this->serializePayoutAccount($r->getPayoutAccountSecondary()),
            'periodStart' => $r->getPeriodStart()?->format('Y-m-d'),
            'periodEnd' => $r->getPeriodEnd()?->format('Y-m-d'),
            'invoiceCount' => $r->getInvoiceCount(),
            'totalAmount' => $r->getTotalAmount(),
            'currency' => $r->getCurrency(),
            'taxPercent' => $r->getTaxPercent(),
            'taxAmount' => $r->getTaxAmount(),
            'netAmount' => $r->getNetAmount(),
            'status' => $r->getStatus(),
            'createdBy' => $r->getCreatedBy(),
            'sentAt' => $r->getSentAt()?->format('Y-m-d H:i:s'),
            'businessApprovedAt' => $r->getBusinessApprovedAt()?->format('Y-m-d H:i:s'),
            'businessApprovedBy' => $r->getBusinessApprovedBy(),
            'adminApprovedAt' => $r->getAdminApprovedAt()?->format('Y-m-d H:i:s'),
            'adminApprovedBy' => $r->getAdminApprovedBy(),
            'rejectedAt' => $r->getRejectedAt()?->format('Y-m-d H:i:s'),
            'rejectedBy' => $r->getRejectedBy(),
            'rejectionReason' => $r->getRejectionReason(),
            'settledAt' => $r->getSettledAt()?->format('Y-m-d H:i:s'),
            'settledBy' => $r->getSettledBy(),
            'settlementMethod' => $r->getSettlementMethod(),
            'settlementReference' => $r->getSettlementReference(),
            'settlementSecondaryMethod' => $r->getSettlementSecondaryMethod(),
            'settlementSecondaryReference' => $r->getSettlementSecondaryReference(),
            'settlementNotes' => $r->getSettlementNotes(),
            'settlementBaseCurrency' => $r->getSettlementBaseCurrency(),
            'settlementBaseAmount' => $r->getSettlementBaseAmount(),
            'settlementBasePercent' => $r->getSettlementBasePercent(),
            'settlementSecondaryCurrency' => $r->getSettlementSecondaryCurrency(),
            'settlementSecondaryAmount' => $r->getSettlementSecondaryAmount(),
            'settlementSecondaryPercent' => $r->getSettlementSecondaryPercent(),
            'settlementExchangeRate' => $r->getSettlementExchangeRate(),
            'createdAt' => $r->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function serializePayoutAccount(?\App\Entity\Balance\BusinessPayoutAccount $p): ?array
    {
        if (!$p) {
            return null;
        }

        return [
            'id' => $p->getId(),
            'alias' => $p->getAlias(),
            'currency' => $p->getCurrency(),
            'accountNumber' => $p->getAccountNumber(),
            'bankName' => $p->getBankName(),
        ];
    }

    private function serializeEvent(BusinessReconciliationEvent $e): array
    {
        return [
            'id' => $e->getId(),
            'eventType' => $e->getEventType(),
            'performedBy' => $e->getPerformedBy(),
            'notes' => $e->getNotes(),
            'metadata' => $e->getMetadata(),
            'createdAt' => $e->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

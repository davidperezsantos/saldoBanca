<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\BusinessReconciliation;
use App\Entity\Balance\BusinessReconciliationEvent;
use App\Entity\Balance\BusinessPayoutAccount;
use App\Entity\Balance\InvoicePayment;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\BusinessReconciliationService;
use App\Services\Balance\InvoiceService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Conciliaciones de negocio (facturas -> payout) desde mobile/ — espejo JSON de
 * Controller/Admin/BusinessReconciliationController (panel Twig/PrimeVue), sin sesión de panel ni
 * CSRF: acá la autorización es el scope OAuth2 (reconciliations_admin.*), no un token CSRF de
 * formulario.
 */
#[OA\Tag(name: 'Reconciliations', description: 'Conciliaciones de negocio (requiere scope reconciliations_admin.*)')]
class BusinessReconciliationController extends BaseController
{
    public function __construct(
        private BusinessReconciliationService $reconciliationService,
        private InvoiceService $invoiceService,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/reconciliations/preview', summary: 'Previsualizar una conciliación', tags: ['Reconciliations'])]
    #[OA\Parameter(name: 'businessAccountId', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'periodStart', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'periodEnd', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[RequireScope('reconciliations_admin.create')]
    #[Route('/admin/reconciliations/preview', name: 'api_admin_reconciliation_preview', methods: ['GET'])]
    public function preview(Request $request): JsonResponse
    {
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

    #[OA\Post(path: '/api/v1/admin/reconciliations', summary: 'Crear una conciliación', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.create')]
    #[Route('/admin/reconciliations', name: 'api_admin_reconciliation_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
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

    #[OA\Get(path: '/api/v1/admin/reconciliations', summary: 'Listar conciliaciones de negocio', tags: ['Reconciliations'])]
    #[OA\Parameter(name: 'businessAccountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 50))]
    #[RequireScope('reconciliations_admin.read')]
    #[Route('/admin/reconciliations', name: 'api_admin_reconciliation_index', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
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

    #[OA\Get(path: '/api/v1/admin/reconciliations/{id}', summary: 'Detalle de una conciliación', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.read')]
    #[Route('/admin/reconciliations/{id}', name: 'api_admin_reconciliation_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
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

    #[OA\Post(path: '/api/v1/admin/reconciliations/{id}/send', summary: 'Enviar conciliación al negocio', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.send')]
    #[Route('/admin/reconciliations/{id}/send', name: 'api_admin_reconciliation_send', methods: ['POST'])]
    public function send(string $id): JsonResponse
    {
        try {
            $reconciliation = $this->reconciliationService->send($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($reconciliation), 'Reconciliation sent');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/reconciliations/{id}/approve', summary: 'Aprobar conciliación (lado admin)', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.approve')]
    #[Route('/admin/reconciliations/{id}/approve', name: 'api_admin_reconciliation_approve', methods: ['PUT'])]
    public function approve(string $id): JsonResponse
    {
        try {
            $reconciliation = $this->reconciliationService->approveByAdmin($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($reconciliation), 'Reconciliation approved');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(path: '/api/v1/admin/reconciliations/{id}/reject', summary: 'Rechazar conciliación', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.approve')]
    #[Route('/admin/reconciliations/{id}/reject', name: 'api_admin_reconciliation_reject', methods: ['PUT'])]
    public function reject(string $id, Request $request): JsonResponse
    {
        try {
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

    #[OA\Put(path: '/api/v1/admin/reconciliations/{id}/settle', summary: 'Liquidar conciliación', tags: ['Reconciliations'])]
    #[RequireScope('reconciliations_admin.settle')]
    #[Route('/admin/reconciliations/{id}/settle', name: 'api_admin_reconciliation_settle', methods: ['PUT'])]
    public function settle(string $id, Request $request): JsonResponse
    {
        try {
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

    private function serializePayoutAccount(?BusinessPayoutAccount $p): ?array
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

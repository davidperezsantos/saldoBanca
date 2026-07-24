<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\CommissionSettlement;
use App\Services\Balance\CommissionSettlementService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commission-settlements')]
class CommissionSettlementController extends BaseController
{
    public function __construct(
        private CommissionSettlementService $settlementService,
    ) {
    }

    #[Route('', name: 'admin_commission_settlements_page', methods: ['GET'])]
    public function page(): Response
    {
        $this->denyAccessUnlessGranted('commission_settlements:view');

        return $this->render('admin/commission_settlements.html.twig');
    }

    #[Route('/available', name: 'admin_commission_settlement_available', methods: ['GET'])]
    public function available(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:view');

        $currency = $request->query->get('currency');
        if (!$currency) {
            return $this->error('currency es requerida');
        }

        return $this->success([
            'currency' => strtoupper($currency),
            'available' => $this->settlementService->getAvailableCommission($currency),
        ]);
    }

    #[Route('/list', name: 'admin_commission_settlement_index', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:view');

        $filters = ['limit' => $request->query->getInt('limit', 50)];
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        if ($request->query->has('currency')) {
            $filters['currency'] = $request->query->get('currency');
        }

        $settlements = $this->settlementService->list($filters);

        return $this->success(array_map(fn(CommissionSettlement $s) => $this->serialize($s), $settlements));
    }

    #[Route('', name: 'admin_commission_settlement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:create');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);

            if (!isset($data['amount']) || !isset($data['currency'])) {
                return $this->error('amount y currency son requeridos');
            }

            $settlement = $this->settlementService->create(
                (string) $data['amount'],
                (string) $data['currency'],
                $data['notes'] ?? null,
                $this->getUser()?->getUserIdentifier()
            );

            return $this->success($this->serialize($settlement), 'Liquidación creada', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}', name: 'admin_commission_settlement_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:details');

        $settlement = $this->settlementService->get($id);
        if (!$settlement) {
            return $this->error('Commission settlement not found', 404);
        }

        return $this->success($this->serialize($settlement));
    }

    #[Route('/{id}/approve', name: 'admin_commission_settlement_approve', methods: ['PUT'])]
    public function approve(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:approve');

        try {
            $this->validateCsrfToken();
            $settlement = $this->settlementService->approveByAdmin($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($settlement), 'Liquidación aprobada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/assign-account', name: 'admin_commission_settlement_assign_account', methods: ['PUT'])]
    public function assignAccount(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:assign_account');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $settlement = $this->settlementService->assignPayoutAccount(
                $id,
                $data['payoutAccountNumber'] ?? '',
                $data['payoutBankName'] ?? null,
                $data['payoutAccountHolder'] ?? null,
                $this->getUser()?->getUserIdentifier()
            );

            return $this->success($this->serialize($settlement), 'Cuenta de pago asignada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/settle', name: 'admin_commission_settlement_settle', methods: ['PUT'])]
    public function settle(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:settle');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $settlement = $this->settlementService->settle(
                $id,
                $data['settlementMethod'] ?? '',
                $data['settlementReference'] ?? null,
                $this->getUser()?->getUserIdentifier()
            );

            return $this->success($this->serialize($settlement), 'Liquidación marcada como pagada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/close', name: 'admin_commission_settlement_close', methods: ['PUT'])]
    public function close(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('commission_settlements:close');

        try {
            $this->validateCsrfToken();
            $settlement = $this->settlementService->close($id, $this->getUser()?->getUserIdentifier());
            return $this->success($this->serialize($settlement), 'Liquidación cerrada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function serialize(CommissionSettlement $s): array
    {
        return [
            'id' => $s->getId(),
            'settlementNumber' => $s->getSettlementNumber(),
            'amount' => $s->getAmount(),
            'currency' => $s->getCurrency(),
            'status' => $s->getStatus(),
            'createdBy' => $s->getCreatedBy(),
            'createdAt' => $s->getCreatedAt()?->format('Y-m-d H:i:s'),
            'notes' => $s->getNotes(),
            'adminApprovedAt' => $s->getAdminApprovedAt()?->format('Y-m-d H:i:s'),
            'adminApprovedBy' => $s->getAdminApprovedBy(),
            'payoutAccountNumber' => $s->getPayoutAccountNumber(),
            'payoutBankName' => $s->getPayoutBankName(),
            'payoutAccountHolder' => $s->getPayoutAccountHolder(),
            'accountAssignedAt' => $s->getAccountAssignedAt()?->format('Y-m-d H:i:s'),
            'accountAssignedBy' => $s->getAccountAssignedBy(),
            'settlementMethod' => $s->getSettlementMethod(),
            'settlementReference' => $s->getSettlementReference(),
            'settledAt' => $s->getSettledAt()?->format('Y-m-d H:i:s'),
            'settledBy' => $s->getSettledBy(),
            'closedAt' => $s->getClosedAt()?->format('Y-m-d H:i:s'),
            'closedBy' => $s->getClosedBy(),
        ];
    }
}

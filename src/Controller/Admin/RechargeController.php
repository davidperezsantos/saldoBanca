<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\RechargeDto;
use App\Services\Balance\RechargeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recharges')]
class RechargeController extends BaseController
{
    public function __construct(
        private RechargeService $rechargeService
    ) {
    }

    #[Route('', name: 'admin_recharges_page')]
    public function rechargesPage(): Response
    {
        $this->denyAccessUnlessGranted('recharges:view');

        return $this->render('admin/recharges.html.twig');
    }

    #[Route('/list', name: 'admin_recharge_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:view');

        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];

        if ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }

        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        if ($request->query->has('rechargeType')) {
            $filters['rechargeType'] = $request->query->get('rechargeType');
        }

        $recharges = $this->rechargeService->listRecharges($filters);

        $data = array_map(function ($recharge) {
            return [
                'id' => $recharge->getId(),
                'accountId' => $recharge->getAccount()->getId(),
                'accountNumber' => $recharge->getAccount()->getAccountNumber(),
                'amount' => $recharge->getAmount(),
                'currency' => $recharge->getCurrency(),
                'originalAmount' => $recharge->getOriginalAmount(),
                'originalCurrency' => $recharge->getOriginalCurrency(),
                'exchangeRate' => $recharge->getExchangeRate(),
                'rechargeType' => $recharge->getRechargeType(),
                'referenceNumber' => $recharge->getReferenceNumber(),
                'status' => $recharge->getStatus(),
                'paymentMethod' => $recharge->getPaymentMethod(),
                'notes' => $recharge->getNotes(),
                'createdAt' => $recharge->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $recharges);

        return $this->success($data);
    }

    #[Route('', name: 'admin_recharge_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:create');

        try {
            $data = $this->getJsonContent($request);
            $dto = RechargeDto::fromJson($data);
            $recharge = $this->rechargeService->createRecharge($dto);

            return $this->success([
                'id' => $recharge->getId(),
                'amount' => $recharge->getAmount(),
                'currency' => $recharge->getCurrency(),
                'status' => $recharge->getStatus(),
            ], 'Recharge created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_recharge_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:details');

        $recharge = $this->rechargeService->getRecharge($id);

        if (!$recharge) {
            return $this->error('Recharge not found', 404);
        }

        return $this->success([
            'id' => $recharge->getId(),
            'accountId' => $recharge->getAccount()->getId(),
            'accountNumber' => $recharge->getAccount()->getAccountNumber(),
            'amount' => $recharge->getAmount(),
            'currency' => $recharge->getCurrency(),
            'originalAmount' => $recharge->getOriginalAmount(),
            'originalCurrency' => $recharge->getOriginalCurrency(),
            'exchangeRate' => $recharge->getExchangeRate(),
            'rechargeType' => $recharge->getRechargeType(),
            'referenceNumber' => $recharge->getReferenceNumber(),
            'externalSystem' => $recharge->getExternalSystem(),
            'status' => $recharge->getStatus(),
            'authorizedBy' => $recharge->getAuthorizedBy(),
            'notes' => $recharge->getNotes(),
            'paymentMethod' => $recharge->getPaymentMethod(),
            'transactionId' => $recharge->getTransactionId(),
            'createdAt' => $recharge->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}/complete', name: 'admin_recharge_complete', methods: ['PUT'])]
    public function complete(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:complete');

        try {
            $recharge = $this->rechargeService->completeRecharge($id);

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge completed successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/fail', name: 'admin_recharge_fail', methods: ['PUT'])]
    public function fail(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:fail');

        try {
            $data = $this->getJsonContent($request);
            $reason = $data['reason'] ?? 'Failed';

            $recharge = $this->rechargeService->failRecharge($id, $reason);

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge marked as failed');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/cancel', name: 'admin_recharge_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:cancel');

        try {
            $recharge = $this->rechargeService->cancelRecharge($id);

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge cancelled successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

}

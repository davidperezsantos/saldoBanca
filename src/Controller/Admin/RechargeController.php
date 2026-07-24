<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\RechargeDto;
use App\Services\Balance\RechargeService;
use App\Services\ExchangeRate\APIExchangeRate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recharges')]
class RechargeController extends BaseController
{
    public function __construct(
        private RechargeService $rechargeService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    /**
     * Convertidor de moneda para el formulario de creación de recarga del panel Admin — antes el
     * frontend llamaba directo a /api/v1/exchange-rate/convert (un endpoint público protegido por
     * scope OAuth2, pensado para sistemas externos, no para el propio panel autenticado por
     * sesión). Declarado antes de la ruta '/{id}' para que Symfony no intente resolver "convert"
     * como un id de recarga.
     */
    #[Route('/convert', name: 'admin_recharge_convert', methods: ['GET'])]
    public function convert(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:create');

        try {
            $amount = $request->query->get('amount');
            $currency = strtoupper($request->query->get('currency', ''));

            if (!$amount || !$currency) {
                return $this->error('amount y currency son requeridos');
            }

            return $this->success($this->apiExchangeRate->convertToBase((string) $amount, $currency));
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('', name: 'admin_recharges_page', methods: ['GET'])]
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
                'receiptNumber' => $recharge->getReceiptNumber(),
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
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $dto = RechargeDto::fromJson($data);
            $recharge = $this->rechargeService->createRecharge($dto, $this->getUser()?->getUserIdentifier());

            return $this->success([
                'id' => $recharge->getId(),
                'receiptNumber' => $recharge->getReceiptNumber(),
                'amount' => $recharge->getAmount(),
                'currency' => $recharge->getCurrency(),
                'status' => $recharge->getStatus(),
            ], 'Recharge created successfully', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
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
            'receiptNumber' => $recharge->getReceiptNumber(),
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
            $this->validateCsrfToken();
            $recharge = $this->rechargeService->completeRecharge($id, $this->getUser()?->getUserIdentifier());

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge completed successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/fail', name: 'admin_recharge_fail', methods: ['PUT'])]
    public function fail(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:fail');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $reason = $data['reason'] ?? 'Failed';

            $recharge = $this->rechargeService->failRecharge($id, $reason, $this->getUser()?->getUserIdentifier());

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge marked as failed');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}/cancel', name: 'admin_recharge_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('recharges:cancel');

        try {
            $this->validateCsrfToken();
            $recharge = $this->rechargeService->cancelRecharge($id, $this->getUser()?->getUserIdentifier());

            return $this->success([
                'id' => $recharge->getId(),
                'status' => $recharge->getStatus(),
            ], 'Recharge cancelled successfully');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

}

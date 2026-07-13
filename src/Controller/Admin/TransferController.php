<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\TransferDto;
use App\Services\Balance\TransferService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/transfers')]
class TransferController extends BaseController
{
    public function __construct(
        private TransferService $transferService
    ) {
    }

    #[Route('', name: 'admin_transfers_page')]
    public function transfersPage(): Response
    {
        $this->denyAccessUnlessGranted('transfers:view');

        return $this->render('admin/transfers.html.twig');
    }

    #[Route('/list', name: 'admin_transfer_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:view');

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

        $transfers = $this->transferService->listTransfers($filters);

        $data = array_map(function ($transfer) {
            return [
                'id' => $transfer->getId(),
                'originAccountId' => $transfer->getOriginAccount()->getId(),
                'originAccountNumber' => $transfer->getOriginAccountNumber(),
                'destinationAccountId' => $transfer->getDestinationAccount()->getId(),
                'destAccountNumber' => $transfer->getDestAccountNumber(),
                'amount' => $transfer->getAmount(),
                'currency' => $transfer->getCurrency(),
                'status' => $transfer->getStatus(),
                'notes' => $transfer->getNotes(),
                'createdAt' => $transfer->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $transfers);

        return $this->success($data);
    }

    #[Route('', name: 'admin_transfer_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:create');

        try {
            $data = $this->getJsonContent($request);
            $dto = TransferDto::fromJson($data);
            $transfer = $this->transferService->createTransfer($dto);

            return $this->success([
                'id' => $transfer->getId(),
                'amount' => $transfer->getAmount(),
                'currency' => $transfer->getCurrency(),
                'status' => $transfer->getStatus(),
                'originAccountNumber' => $transfer->getOriginAccountNumber(),
                'destAccountNumber' => $transfer->getDestAccountNumber(),
            ], 'Transfer created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_transfer_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:details');

        $transfer = $this->transferService->getTransfer($id);

        if (!$transfer) {
            return $this->error('Transfer not found', 404);
        }

        return $this->success([
            'id' => $transfer->getId(),
            'originAccountId' => $transfer->getOriginAccount()->getId(),
            'originAccountNumber' => $transfer->getOriginAccountNumber(),
            'destinationAccountId' => $transfer->getDestinationAccount()->getId(),
            'destAccountNumber' => $transfer->getDestAccountNumber(),
            'amount' => $transfer->getAmount(),
            'currency' => $transfer->getCurrency(),
            'status' => $transfer->getStatus(),
            'authorizedBy' => $transfer->getAuthorizedBy(),
            'notes' => $transfer->getNotes(),
            'fee' => $transfer->getFee(),
            'createdAt' => $transfer->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}/process', name: 'admin_transfer_process', methods: ['PUT'])]
    public function process(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:process');

        try {
            $transfer = $this->transferService->processTransfer($id);

            return $this->success([
                'id' => $transfer->getId(),
                'status' => $transfer->getStatus(),
            ], 'Transfer processed successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/cancel', name: 'admin_transfer_cancel', methods: ['PUT'])]
    public function cancel(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:cancel');

        try {
            $transfer = $this->transferService->cancelTransfer($id);

            return $this->success([
                'id' => $transfer->getId(),
                'status' => $transfer->getStatus(),
            ], 'Transfer cancelled successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/limits/{accountId}', name: 'admin_transfer_limits', methods: ['GET'])]
    public function limits(string $accountId): JsonResponse
    {
        $this->denyAccessUnlessGranted('transfers:limits');

        try {
            $limits = $this->transferService->getTransferLimits($accountId);

            return $this->success($limits);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

}

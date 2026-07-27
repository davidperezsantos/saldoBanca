<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\Balance\BusinessPayoutAccount;
use App\Services\Balance\BusinessPayoutAccountService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/accounts/{accountId}/payout-accounts')]
class BusinessPayoutAccountController extends BaseController
{
    #[Route('', name: 'admin_business_payout_account_index', methods: ['GET'])]
    public function index(string $accountId, BusinessPayoutAccountService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('payout_accounts:view');

        try {
            $accounts = $service->listByAccount($accountId);

            return $this->success(array_map([$this, 'serialize'], $accounts));
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('', name: 'admin_business_payout_account_create', methods: ['POST'])]
    public function create(string $accountId, Request $request, BusinessPayoutAccountService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('payout_accounts:create');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $payoutAccount = $service->create($accountId, $data);

            return $this->success($this->serialize($payoutAccount), 'Cuenta de pago creada', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}', name: 'admin_business_payout_account_update', methods: ['PUT'])]
    public function update(string $accountId, string $id, Request $request, BusinessPayoutAccountService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('payout_accounts:edit');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $payoutAccount = $service->update($accountId, $id, $data);

            return $this->success($this->serialize($payoutAccount), 'Cuenta de pago actualizada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[Route('/{id}', name: 'admin_business_payout_account_delete', methods: ['DELETE'])]
    public function delete(string $accountId, string $id, BusinessPayoutAccountService $service): JsonResponse
    {
        $this->denyAccessUnlessGranted('payout_accounts:delete');

        try {
            $this->validateCsrfToken();
            $service->delete($accountId, $id);

            return $this->success(null, 'Cuenta de pago eliminada');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    private function serialize(BusinessPayoutAccount $payoutAccount): array
    {
        return [
            'id' => $payoutAccount->getId(),
            'alias' => $payoutAccount->getAlias(),
            'currency' => $payoutAccount->getCurrency(),
            'bankName' => $payoutAccount->getBankName(),
            'accountNumber' => $payoutAccount->getAccountNumber(),
            'swift' => $payoutAccount->getSwift(),
            'accountHolder' => $payoutAccount->getAccountHolder(),
            'isActive' => $payoutAccount->isActive(),
            'createdAt' => $payoutAccount->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\AuthorizedDto;
use App\Services\Balance\AuthorizedService;
use App\Services\Balance\BalanceService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\SystemCurrencyService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/authorized')]
class AuthorizedController extends BaseController
{
    public function __construct(
        private AuthorizedService $authorizedService,
        private BalanceService $balanceService,
        private SystemCurrencyService $systemCurrencyService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    #[Route('', name: 'admin_authorized_page', methods: ['GET'])]
    public function authorizedPage(): Response
    {
        $this->denyAccessUnlessGranted('authorized:view');

        return $this->render('admin/authorized.html.twig');
    }

    #[Route('/list', name: 'admin_authorized_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:view');

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

        $authorizedUsers = $this->authorizedService->listAuthorized($filters);

        $data = array_map(function ($authorized) {
            $account = $authorized->getAccount();
            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            // El saldo real vive en la moneda base (todo lo que entra se convierte a esa moneda),
            // no necesariamente en account.defaultCurrency — buscar directo por defaultCurrency
            // devolvía "0.00" para cuentas sin ninguna fila de AccountBalance en esa moneda,
            // aunque sí tuvieran saldo real en otra. Se busca por la base y se convierte para
            // mostrar, igual que Admin\AccountController::index().
            $baseBalance = $this->balanceService->getBalance($account->getId()->toString(), $baseCurrency);
            $displayCurrency = $account->getDefaultCurrency();
            $available = $baseBalance ? $baseBalance->getAvailableBalance() : '0.00';

            if ($displayCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($displayCurrency);
                if ($rate !== null) {
                    $available = (string) round((float) bcmul($available, (string) $rate, 4), 2);
                }
            }

            return [
                'id' => $authorized->getId(),
                'accountId' => $account->getId(),
                'saldoDisponible' => $available,
                'moneda' => $displayCurrency,
                'saldoBase' => $baseBalance ? $baseBalance->getAvailableBalance() : '0.00',
                'baseCurrency' => $baseCurrency,
                'userName' => $authorized->getUserName(),
                'userEmail' => $authorized->getUserEmail(),
                'userPhone' => $authorized->getUserPhone(),
                'documentType' => $authorized->getDocumentType(),
                'documentNumber' => $authorized->getDocumentNumber(),
                'maxAmount' => $authorized->getMaxAmount(),
                'dailyLimit' => $authorized->getDailyLimit(),
                'monthlyLimit' => $authorized->getMonthlyLimit(),
                'usedToday' => $authorized->getUsedToday(),
                'usedThisMonth' => $authorized->getUsedThisMonth(),
                'status' => $authorized->getStatus(),
                'lastUsedAt' => $authorized->getLastUsedAt()?->format('Y-m-d H:i:s'),
                'createdAt' => $authorized->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $authorizedUsers);

        return $this->success($data);
    }

    #[Route('', name: 'admin_authorized_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:create');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $password = bin2hex(random_bytes(8));
            $authorized = $this->authorizedService->createAuthorized($dto, $password);

            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'documentNumber' => $authorized->getDocumentNumber(),
                'status' => $authorized->getStatus(),
                'username' => $authorized->getUser()?->getUsername(),
                'password' => $password,
            ], 'Usuario autorizado creado exitosamente', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_authorized_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:details');

        $authorized = $this->authorizedService->listAuthorized(['accountId' => $id]);

        if (empty($authorized)) {
            return $this->error('Authorized user not found', 404);
        }

        return $this->success($authorized[0]);
    }

    #[Route('/{id}', name: 'admin_authorized_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:edit');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $authorized = $this->authorizedService->updateAuthorized($id, $dto);

            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_authorized_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:delete');

        try {
            $this->validateCsrfToken();
            $this->authorizedService->deleteAuthorized($id);

            return $this->success(null, 'Authorized user deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/status', name: 'admin_authorized_status', methods: ['PUT'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:status');

        try {
            $this->validateCsrfToken();
            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? null;

            if (!$status) {
                return $this->error('Status is required');
            }

            $authorized = $this->authorizedService->changeStatus($id, $status);

            return $this->success([
                'id' => $authorized->getId(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user status updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/verify/{documentNumber}', name: 'admin_authorized_verify', methods: ['GET'])]
    public function verify(string $documentNumber): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:verify');

        $authorized = $this->authorizedService->verifyAuthorized($documentNumber);

        if (!$authorized) {
            return $this->error('Authorized user not found', 404);
        }

        return $this->success([
            'id' => $authorized->getId(),
            'userName' => $authorized->getUserName(),
            'documentNumber' => $authorized->getDocumentNumber(),
            'status' => $authorized->getStatus(),
            'accountNumber' => $authorized->getAccount()->getAccountNumber(),
        ]);
    }

    #[Route('/{id}/reset-password', name: 'admin_authorized_reset_password', methods: ['POST'])]
    public function resetPassword(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('authorized:edit');

        try {
            $this->validateCsrfToken();
            $token = bin2hex(random_bytes(32));
            $this->authorizedService->saveResetToken($id, $token);

            $resetUrl = $this->generateUrl('app_reset_password_confirm', ['token' => $token], true);
            $this->authorizedService->resetPassword($id, $resetUrl);

            return $this->success([], 'Enlace de restablecimiento enviado por WhatsApp');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\AccountDto;
use App\Services\Balance\AccountService;
use App\Services\Balance\BalanceService;
use App\Services\RegistrationService;
use App\Services\SystemCurrencyService;
use App\Services\ExchangeRate\APIExchangeRate;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/accounts')]
class AccountController extends BaseController
{
    public function __construct(
        private AccountService $accountService,
        private BalanceService $balanceService,
        private RegistrationService $registrationService,
        private JWTTokenManagerInterface $jwtManager,
        private SystemCurrencyService $systemCurrencyService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    #[Route('', name: 'admin_accounts_page', methods: ['GET'])]
    public function accountsPage(): Response
    {
        $this->denyAccessUnlessGranted('clients:view');

        $accounts = $this->accountService->listAccounts([]);

        return $this->render('admin/accounts.html.twig', [
            'accounts' => $accounts,
        ]);
    }

    #[Route('/list', name: 'admin_account_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:view');

        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];

        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        if ($request->query->has('accountType')) {
            $filters['accountType'] = $request->query->get('accountType');
        }

        if ($request->query->has('search')) {
            $filters['search'] = $request->query->get('search');
        }

        $accounts = $this->accountService->listAccounts($filters);

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $data = array_map(function ($account) use ($baseCurrency) {
            $balance = $this->balanceService->getBalance($account->getId()->toString(), $baseCurrency);
            $available = $balance ? $balance->getAvailableBalance() : '0.00';
            $displayCurrency = $account->getDefaultCurrency();

            if ($displayCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($displayCurrency);
                if ($rate !== null) {
                    $available = bcmul($available, (string)$rate, 2);
                }
            }

            return [
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'accountType' => $account->getAccountType(),
                'businessName' => $account->getBusinessName(),
                'documentType' => $account->getDocumentType(),
                'documentNumber' => $account->getDocumentNumber(),
                'email' => $account->getEmail(),
                'phone' => $account->getPhone(),
                'status' => $account->getStatus(),
                'defaultCurrency' => $displayCurrency,
                'creditLimit' => $account->getCreditLimit(),
                'saldoDisponible' => $available,
                'createdAt' => $account->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $accounts);

        return $this->success($data);
    }

    #[Route('', name: 'admin_account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:create');

        try {
            $data = $this->getJsonContent($request);
            $dto = AccountDto::fromJson($data);
            $account = $this->accountService->createAccount($dto);

            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account created successfully', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}', name: 'admin_account_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:details');

        $account = $this->accountService->getAccount($id);

        if (!$account) {
            return $this->error('Account not found', 404);
        }

        $summary = $this->accountService->getAccountSummary($id);

        return $this->success($summary);
    }

    #[Route('/{id}', name: 'admin_account_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:edit');

        try {
            $data = $this->getJsonContent($request);
            $dto = AccountDto::fromJson($data);
            $account = $this->accountService->updateAccount($id, $dto);

            return $this->success([
                'id' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'status' => $account->getStatus(),
            ], 'Account updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/status', name: 'admin_account_status', methods: ['PUT'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:status');

        try {
            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? null;

            if (!$status) {
                return $this->error('Status is required');
            }

            $account = $this->accountService->changeStatus($id, $status);

            return $this->success([
                'id' => $account->getId(),
                'status' => $account->getStatus(),
            ], 'Account status updated successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[Route('/{id}/balance', name: 'admin_account_balance', methods: ['GET'])]
    public function balance(string $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('clients:balance');

        $account = $this->accountService->getAccount($id);

        if (!$account) {
            return $this->error('Account not found', 404);
        }

        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
        $balance = $this->balanceService->getBalance($id, $baseCurrency);

        $available = $balance ? $balance->getAvailableBalance() : '0.00';
        $pending = $balance ? $balance->getPendingBalance() : '0.00';
        $reserved = $balance ? $balance->getReservedBalance() : '0.00';
        $displayCurrency = $account->getDefaultCurrency();

        if ($displayCurrency !== $baseCurrency) {
            $rate = $this->apiExchangeRate->getRate($displayCurrency);
            if ($rate !== null) {
                $available = bcmul($available, (string)$rate, 2);
                $pending = bcmul($pending, (string)$rate, 2);
                $reserved = bcmul($reserved, (string)$rate, 2);
            }
        }

        return $this->success([
            'available' => $available,
            'pending' => $pending,
            'reserved' => $reserved,
            'currency' => $displayCurrency,
            'totalRecharged' => $balance ? $balance->getTotalRecharged() : '0.00',
            'totalTransferred' => $balance ? $balance->getTotalTransferred() : '0.00',
            'totalInvoiced' => $balance ? $balance->getTotalInvoiced() : '0.00',
        ]);
    }

    #[Route('/{id}/approve', name: 'admin_account_approve_business', methods: ['POST'])]
    public function approveBusiness(string $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('businesses:edit');

        try {
            $data = $this->getJsonContent($request);
            $user = $this->registrationService->approveBusiness($id, $data);

            $token = $this->jwtManager->create($user);

            return $this->success([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName(),
                ],
            ], 'Business approved successfully');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

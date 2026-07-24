<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\Balance\AccountService;
use App\Services\Balance\BalanceService;
use App\Services\SystemCurrencyService;
use App\Services\ExchangeRate\APIExchangeRate;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Balance', description: 'Consultas de saldo')]
class BalanceController extends BaseController
{
    public function __construct(
        private BalanceService $balanceService,
        private AccountService $accountService,
        private SystemCurrencyService $systemCurrencyService,
        private APIExchangeRate $apiExchangeRate,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/balance/{accountId}',
        summary: 'Consultar saldo',
        description: 'Obtiene el saldo disponible, pendiente y reservado de una cuenta.',
        tags: ['Balance'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'currency', in: 'query', description: 'Código de moneda (USD, EUR, etc.)', schema: new OA\Schema(type: 'string', default: 'USD'))]
    #[OA\Response(
        response: 200,
        description: 'Saldo de la cuenta',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'accountId', type: 'string', example: 'a1b2c3d4-...'),
                    new OA\Property(property: 'available', type: 'string', example: '1500.00'),
                    new OA\Property(property: 'pending', type: 'string', example: '200.00'),
                    new OA\Property(property: 'reserved', type: 'string', example: '100.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error al consultar saldo')]
    #[RequireScope('balance.read')]
    #[Route('/balance/{accountId}', name: 'api_balance_show', methods: ['GET'])]
    public function show(string $accountId, Request $request): JsonResponse
    {
        try {
            // Usuario final (JWT): solo puede consultar el saldo de SU propia cuenta — sin este
            // chequeo, cualquier usuario podría leer el saldo de otra cuenta cambiando el id
            // (IDOR). Un cliente OAuth2 de negocio no tiene esta restricción.
            $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
            if ($selfServiceUser !== null
                && (string) $selfServiceUser->getAccount()?->getId() !== $accountId
            ) {
                return $this->error('No podés consultar el saldo de esta cuenta', 403);
            }

            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            $balance = $this->balanceService->getBalance($accountId, $baseCurrency);
            $available = $balance ? $balance->getAvailableBalance() : '0.00';
            $pending = $balance ? $balance->getPendingBalance() : '0.00';
            $reserved = $balance ? $balance->getReservedBalance() : '0.00';

            $requestCurrency = $request->query->get('currency');
            if ($requestCurrency && $requestCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($requestCurrency);
                if ($rate !== null) {
                    $available = bcmul($available, (string)$rate, 2);
                    $pending = bcmul($pending, (string)$rate, 2);
                    $reserved = bcmul($reserved, (string)$rate, 2);
                }
            }

            return $this->success([
                'accountId' => $accountId,
                'available' => $available,
                'pending' => $pending,
                'reserved' => $reserved,
                'currency' => $requestCurrency ?? $baseCurrency,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/balance/check',
        summary: 'Verificar saldo por número de cuenta',
        description: 'Consulta el saldo disponible usando el número de cuenta. Requiere API Key.',
        tags: ['Balance'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['accountNumber'],
            properties: [
                new OA\Property(property: 'accountNumber', description: 'Número de cuenta', type: 'string', example: '123-456-789'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Saldo disponible',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                    new OA\Property(property: 'businessName', type: 'string', example: 'Empresa S.A.'),
                    new OA\Property(property: 'available', type: 'string', example: '1500.00'),
                    new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Account number is required')]
    #[OA\Response(response: 401, description: 'Token OAuth2 inválido o ausente')]
    #[OA\Response(response: 403, description: 'Scope insuficiente (requiere "balance")')]
    #[OA\Response(response: 404, description: 'Account not found')]
    #[RequireScope('balance.read')]
    #[Route('/balance/check', name: 'api_balance_check', methods: ['POST'])]
    public function check(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $accountNumber = $data['accountNumber'] ?? null;
            if (!$accountNumber) {
                return $this->error('Account number is required');
            }
            $account = $this->accountService->getAccountByNumber($accountNumber);
            if (!$account) {
                return $this->error('Account not found', 404);
            }

            $selfServiceUser = $this->scopeAuthorizationService->getSelfServiceUser();
            if ($selfServiceUser !== null
                && (string) $selfServiceUser->getAccount()?->getId() !== (string) $account->getId()
            ) {
                return $this->error('No podés consultar el saldo de esta cuenta', 403);
            }

            $baseCurrency = $this->systemCurrencyService->getBaseCurrency();
            $balance = $this->balanceService->getBalance(
                $account->getId()->toString(),
                $baseCurrency
            );
            $available = $balance ? $balance->getAvailableBalance() : '0.00';
            $displayCurrency = $account->getDefaultCurrency();

            if ($displayCurrency !== $baseCurrency) {
                $rate = $this->apiExchangeRate->getRate($displayCurrency);
                if ($rate !== null) {
                    $available = bcmul($available, (string)$rate, 2);
                }
            }

            return $this->success([
                'accountNumber' => $account->getAccountNumber(),
                'businessName' => $account->getBusinessName(),
                'available' => $available,
                'currency' => $displayCurrency,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\Entity\Balance\BusinessPayoutAccount;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\BusinessPayoutAccountService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'PayoutAccounts', description: 'Cuentas reales de pago de un negocio (a dónde transferirle al liquidar una conciliación)')]
class BusinessPayoutAccountController extends BaseController
{
    public function __construct(
        private BusinessPayoutAccountService $payoutAccountService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/accounts/{accountId}/payout-accounts',
        summary: 'Listar cuentas de pago de un negocio',
        description: 'Devuelve las cuentas reales (bancarias/de pago) registradas para una cuenta de tipo negocio.',
        tags: ['PayoutAccounts'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta de negocio', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Lista de cuentas de pago',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'a1b2c3d4-...'),
                        new OA\Property(property: 'alias', type: 'string', example: 'Cuenta principal USD'),
                        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'bankName', type: 'string', example: 'Banco Ejemplo'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '1234567890'),
                        new OA\Property(property: 'swift', type: 'string', example: 'EXAMPLUS'),
                        new OA\Property(property: 'accountHolder', type: 'string', example: 'Empresa S.A.'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    ]
                ))
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'La cuenta no existe o no es de tipo negocio')]
    #[RequireScope('payout_accounts.read')]
    #[Route('/accounts/{accountId}/payout-accounts', name: 'api_business_payout_account_list', methods: ['GET'])]
    public function list(string $accountId): JsonResponse
    {
        try {
            $accounts = $this->payoutAccountService->listByAccount($accountId);
            return $this->success(array_map([$this, 'serialize'], $accounts));
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Post(
        path: '/api/v1/accounts/{accountId}/payout-accounts',
        summary: 'Registrar una cuenta de pago',
        description: 'Crea una cuenta real (bancaria/de pago) para que el negocio indique a dónde debe transferírsele el dinero al liquidar una conciliación.',
        tags: ['PayoutAccounts'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta de negocio', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['alias', 'currency', 'accountNumber'],
            properties: [
                new OA\Property(property: 'alias', description: 'Nombre para identificar la cuenta', type: 'string', example: 'Cuenta principal USD'),
                new OA\Property(property: 'currency', description: 'Moneda de la cuenta (código de 3 letras)', type: 'string', example: 'USD'),
                new OA\Property(property: 'accountNumber', description: 'Número de cuenta', type: 'string', example: '1234567890'),
                new OA\Property(property: 'bankName', description: 'Nombre del banco (opcional)', type: 'string', example: 'Banco Ejemplo'),
                new OA\Property(property: 'swift', description: 'Código SWIFT/BIC (opcional)', type: 'string', example: 'EXAMPLUS'),
                new OA\Property(property: 'accountHolder', description: 'Titular de la cuenta (opcional)', type: 'string', example: 'Empresa S.A.'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Cuenta de pago creada')]
    #[OA\Response(response: 400, description: 'Error de validación o la cuenta no es de tipo negocio')]
    #[RequireScope('payout_accounts.create')]
    #[Route('/accounts/{accountId}/payout-accounts', name: 'api_business_payout_account_create', methods: ['POST'])]
    public function create(string $accountId, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $payoutAccount = $this->payoutAccountService->create($accountId, $data);
            return $this->success($this->serialize($payoutAccount), 'Payout account created', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/accounts/{accountId}/payout-accounts/{id}',
        summary: 'Actualizar una cuenta de pago',
        description: 'Actualiza los datos de una cuenta de pago existente del negocio.',
        tags: ['PayoutAccounts'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta de negocio', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la cuenta de pago', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['alias', 'currency', 'accountNumber'],
            properties: [
                new OA\Property(property: 'alias', type: 'string', example: 'Cuenta principal USD'),
                new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                new OA\Property(property: 'accountNumber', type: 'string', example: '1234567890'),
                new OA\Property(property: 'bankName', type: 'string', example: 'Banco Ejemplo'),
                new OA\Property(property: 'swift', type: 'string', example: 'EXAMPLUS'),
                new OA\Property(property: 'accountHolder', type: 'string', example: 'Empresa S.A.'),
                new OA\Property(property: 'isActive', description: 'Activa/inactiva para selección al aprobar conciliaciones', type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Payout account updated')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 404, description: 'Payout account not found')]
    #[RequireScope('payout_accounts.update')]
    #[Route('/accounts/{accountId}/payout-accounts/{id}', name: 'api_business_payout_account_update', methods: ['PUT'])]
    public function update(string $accountId, string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $payoutAccount = $this->payoutAccountService->update($accountId, $id, $data);
            return $this->success($this->serialize($payoutAccount), 'Payout account updated');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/accounts/{accountId}/payout-accounts/{id}',
        summary: 'Eliminar una cuenta de pago',
        description: 'Elimina una cuenta de pago del negocio.',
        tags: ['PayoutAccounts'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'path', description: 'ID de la cuenta de negocio', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID de la cuenta de pago', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Payout account deleted')]
    #[OA\Response(response: 404, description: 'Payout account not found')]
    #[RequireScope('payout_accounts.delete')]
    #[Route('/accounts/{accountId}/payout-accounts/{id}', name: 'api_business_payout_account_delete', methods: ['DELETE'])]
    public function delete(string $accountId, string $id): JsonResponse
    {
        try {
            $this->payoutAccountService->delete($accountId, $id);
            return $this->success(null, 'Payout account deleted');
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

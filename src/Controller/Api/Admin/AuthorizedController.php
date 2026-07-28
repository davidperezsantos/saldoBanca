<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\DTO\Balance\AuthorizedDto;
use App\Security\Attribute\RequireScope;
use App\Services\Balance\AuthorizedService;
use App\Services\Balance\BalanceService;
use App\Services\ExchangeRate\APIExchangeRate;
use App\Services\SystemCurrencyService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de usuarios autorizados de TODAS las cuentas desde mobile/ — espejo JSON de
 * Controller/Admin/AuthorizedController (panel Twig). No confundir con Controller/Api/AuthorizedController
 * (self-service, autorizados de "mi propia cuenta"): acá el caller es staff.
 */
#[OA\Tag(name: 'Admin Authorized', description: 'Usuarios autorizados del sistema (requiere scope authorized_admin.*)')]
class AuthorizedController extends BaseController
{
    public function __construct(
        private AuthorizedService $authorizedService,
        private BalanceService $balanceService,
        private SystemCurrencyService $systemCurrencyService,
        private APIExchangeRate $apiExchangeRate,
    ) {
    }

    #[OA\Get(path: '/api/v1/admin/authorized', summary: 'Listar autorizados del sistema', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'accountId', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Lista de autorizados')]
    #[RequireScope('authorized_admin.read')]
    #[Route('/admin/authorized', name: 'api_admin_authorized_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'limit' => $request->query->getInt('limit', 20),
            'offset' => $request->query->getInt('offset', 0),
        ];
        foreach (['accountId', 'status'] as $key) {
            if ($request->query->has($key)) {
                $filters[$key] = $request->query->get($key);
            }
        }

        $authorizedUsers = $this->authorizedService->listAuthorized($filters);
        $baseCurrency = $this->systemCurrencyService->getBaseCurrency();

        $data = array_map(function ($authorized) use ($baseCurrency) {
            $account = $authorized->getAccount();
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
                'accountNumber' => $account->getAccountNumber(),
                'saldoDisponible' => $available,
                'moneda' => $displayCurrency,
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

    #[OA\Post(path: '/api/v1/admin/authorized', summary: 'Crear autorizado', tags: ['Admin Authorized'])]
    #[OA\Response(response: 201, description: 'Autorizado creado')]
    #[RequireScope('authorized_admin.create')]
    #[Route('/admin/authorized', name: 'api_admin_authorized_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = AuthorizedDto::fromJson($this->getJsonContent($request));
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

    #[OA\Put(path: '/api/v1/admin/authorized/{id}', summary: 'Actualizar autorizado', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Autorizado actualizado')]
    #[RequireScope('authorized_admin.update')]
    #[Route('/admin/authorized/{id}', name: 'api_admin_authorized_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $dto = AuthorizedDto::fromJson($this->getJsonContent($request));
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

    #[OA\Delete(path: '/api/v1/admin/authorized/{id}', summary: 'Eliminar autorizado', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Autorizado eliminado')]
    #[RequireScope('authorized_admin.delete')]
    #[Route('/admin/authorized/{id}', name: 'api_admin_authorized_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->authorizedService->deleteAuthorized($id);

            return $this->success(null, 'Authorized user deleted successfully');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(path: '/api/v1/admin/authorized/{id}/status', summary: 'Cambiar estado de un autorizado', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado actualizado')]
    #[RequireScope('authorized_admin.status')]
    #[Route('/admin/authorized/{id}/status', name: 'api_admin_authorized_status', methods: ['PUT'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        try {
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

    #[OA\Get(path: '/api/v1/admin/authorized/verify/{documentNumber}', summary: 'Verificar autorizado por documento', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'documentNumber', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Autorizado encontrado')]
    #[OA\Response(response: 404, description: 'Authorized user not found')]
    #[RequireScope('authorized_admin.verify')]
    #[Route('/admin/authorized/verify/{documentNumber}', name: 'api_admin_authorized_verify', methods: ['GET'])]
    public function verify(string $documentNumber): JsonResponse
    {
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

    #[OA\Post(path: '/api/v1/admin/authorized/{id}/reset-password', summary: 'Restablecer contraseña de un autorizado', tags: ['Admin Authorized'])]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Enlace enviado por WhatsApp')]
    #[RequireScope('authorized_admin.update')]
    #[Route('/admin/authorized/{id}/reset-password', name: 'api_admin_authorized_reset_password', methods: ['POST'])]
    public function resetPassword(string $id): JsonResponse
    {
        try {
            $token = bin2hex(random_bytes(32));
            $this->authorizedService->saveResetToken($id, $token);

            $resetUrl = $this->generateUrl('app_reset_password_confirm', ['token' => $token], true);
            $this->authorizedService->resetPassword($id, $resetUrl);

            return $this->success(null, 'Enlace de restablecimiento enviado por WhatsApp');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

<?php

namespace App\Controller\Api;

use App\Controller\BaseController;
use App\DTO\Balance\AuthorizedDto;
use App\Services\Balance\AuthorizedService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Authorized', description: 'Usuarios autorizados')]
class AuthorizedController extends BaseController
{
    public function __construct(
        private AuthorizedService $authorizedService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/authorized',
        summary: 'Listar usuarios autorizados',
        description: 'Obtiene los usuarios autorizados para operar en cuentas.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'accountId', in: 'query', description: 'Filtrar por ID de cuenta', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Filtrar por estado (active, inactive)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de registros', schema: new OA\Schema(type: 'integer', default: 50))]
    #[OA\Response(
        response: 200,
        description: 'Lista de usuarios autorizados',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                        new OA\Property(property: 'accountId', type: 'string', example: 'a1-...'),
                        new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                        new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                        new OA\Property(property: 'userEmail', type: 'string', example: 'carlos@ejemplo.com'),
                        new OA\Property(property: 'documentType', type: 'string', example: 'CE'),
                        new OA\Property(property: 'documentNumber', type: 'string', example: '123456789'),
                        new OA\Property(property: 'maxAmount', type: 'string', example: '5000.00'),
                        new OA\Property(property: 'dailyLimit', type: 'string', example: '10000.00'),
                        new OA\Property(property: 'monthlyLimit', type: 'string', example: '100000.00'),
                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                        new OA\Property(property: 'createdAt', type: 'string', example: '2024-01-01 12:00:00'),
                    ]
                ))
            ]
        )
    )]
    #[Route('/authorized', name: 'api_authorized_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $filters = [];
        if ($request->query->has('accountId')) {
            $filters['accountId'] = $request->query->get('accountId');
        }
        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }
        $filters['limit'] = $request->query->getInt('limit', 50);

        $authorized = $this->authorizedService->listAuthorized($filters);
        $data = array_map(fn($a) => [
            'id' => $a->getId(),
            'accountId' => $a->getAccount()->getId(),
            'accountNumber' => $a->getAccount()->getAccountNumber(),
            'userName' => $a->getUserName(),
            'userEmail' => $a->getUserEmail(),
            'documentType' => $a->getDocumentType(),
            'documentNumber' => $a->getDocumentNumber(),
            'maxAmount' => $a->getMaxAmount(),
            'dailyLimit' => $a->getDailyLimit(),
            'monthlyLimit' => $a->getMonthlyLimit(),
            'status' => $a->getStatus(),
            'createdAt' => $a->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $authorized);

        return $this->success($data);
    }

    #[OA\Post(
        path: '/api/v1/authorized',
        summary: 'Crear usuario autorizado',
        description: 'Registra un nuevo usuario autorizado para operar en una cuenta.',
        tags: ['Authorized'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'accountId', description: 'ID de la cuenta', type: 'string', example: 'a1b2c3d4-...'),
                new OA\Property(property: 'userName', description: 'Nombre del usuario', type: 'string', example: 'Carlos López'),
                new OA\Property(property: 'userEmail', description: 'Correo electrónico (opcional)', type: 'string', example: 'carlos@ejemplo.com'),
                new OA\Property(property: 'documentType', description: 'Tipo de documento', type: 'string', example: 'CE'),
                new OA\Property(property: 'documentNumber', description: 'Número de documento', type: 'string', example: '123456789'),
                new OA\Property(property: 'maxAmount', description: 'Monto máximo por operación (opcional)', type: 'number', example: 5000.00),
                new OA\Property(property: 'dailyLimit', description: 'Límite diario (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'monthlyLimit', description: 'Límite mensual (opcional)', type: 'number', example: 100000.00),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Usuario autorizado creado exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                    new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[Route('/authorized', name: 'api_authorized_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $authorized = $this->authorizedService->createAuthorized($dto);
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user created', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(
        path: '/api/v1/authorized/{id}',
        summary: 'Actualizar usuario autorizado',
        description: 'Actualiza los datos de un usuario autorizado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'userName', description: 'Nombre del usuario', type: 'string', example: 'Carlos López'),
                new OA\Property(property: 'userEmail', description: 'Correo electrónico (opcional)', type: 'string', example: 'carlos@ejemplo.com'),
                new OA\Property(property: 'documentType', description: 'Tipo de documento', type: 'string', example: 'CE'),
                new OA\Property(property: 'documentNumber', description: 'Número de documento', type: 'string', example: '123456789'),
                new OA\Property(property: 'maxAmount', description: 'Monto máximo por operación (opcional)', type: 'number', example: 5000.00),
                new OA\Property(property: 'dailyLimit', description: 'Límite diario (opcional)', type: 'number', example: 10000.00),
                new OA\Property(property: 'monthlyLimit', description: 'Límite mensual (opcional)', type: 'number', example: 100000.00),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Authorized user updated')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[Route('/authorized/{id}', name: 'api_authorized_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $dto = AuthorizedDto::fromJson($data);
            $authorized = $this->authorizedService->updateAuthorized($id, $dto);
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
            ], 'Authorized user updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Put(
        path: '/api/v1/authorized/{id}/status',
        summary: 'Cambiar estado de usuario autorizado',
        description: 'Activa o desactiva un usuario autorizado.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', description: 'ID del usuario autorizado', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'status', description: 'Nuevo estado (active, inactive)', type: 'string', example: 'inactive'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Status updated')]
    #[OA\Response(response: 400, description: 'Error al cambiar estado')]
    #[Route('/authorized/{id}/status', name: 'api_authorized_status', methods: ['PUT'])]
    public function toggleStatus(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->getJsonContent($request);
            $status = $data['status'] ?? 'active';
            $authorized = $this->authorizedService->changeStatus($id, $status);
            return $this->success([
                'id' => $authorized->getId(),
                'status' => $authorized->getStatus(),
            ], 'Status updated');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    #[OA\Get(
        path: '/api/v1/authorized/{doc}/verify',
        summary: 'Verificar usuario autorizado por documento',
        description: 'Busca y verifica un usuario autorizado por su número de documento.',
        tags: ['Authorized'],
    )]
    #[OA\Parameter(name: 'doc', in: 'path', description: 'Número de documento', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Usuario autorizado encontrado',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'id', type: 'string', example: 'au1-...'),
                    new OA\Property(property: 'userName', type: 'string', example: 'Carlos López'),
                    new OA\Property(property: 'status', type: 'string', example: 'active'),
                    new OA\Property(property: 'accountNumber', type: 'string', example: '123-456-789'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Authorized user not found')]
    #[Route('/authorized/{doc}/verify', name: 'api_authorized_verify', methods: ['GET'])]
    public function verify(string $doc): JsonResponse
    {
        try {
            $authorized = $this->authorizedService->verifyAuthorized($doc);
            if (!$authorized) {
                return $this->error('Authorized user not found', 404);
            }
            return $this->success([
                'id' => $authorized->getId(),
                'userName' => $authorized->getUserName(),
                'status' => $authorized->getStatus(),
                'accountNumber' => $authorized->getAccount()->getAccountNumber(),
            ]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

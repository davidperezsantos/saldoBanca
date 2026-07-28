<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\DTO\Admin\CreateStaffUserDto;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use App\Services\StaffUserService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de usuarios staff (super_admin/admin/support) desde mobile/ — equivalente en JSON del
 * panel Twig (Controller/Admin/UserController), inalcanzable desde la app porque usa sesión de
 * cookie en vez de JWT. Ver StaffUserService para la lógica compartida de validación/ownership.
 */
#[OA\Tag(name: 'Admin Users', description: 'Gestión de usuarios staff (requiere scope users.*)')]
class UserController extends BaseController
{
    public function __construct(
        private StaffUserService $staffUserService,
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/users',
        summary: 'Listar usuarios staff',
        description: 'Devuelve los usuarios staff visibles para el rol activo (los de rol de sistema quedan ocultos salvo que el viewer también lo sea) junto con el catálogo de roles asignables.',
        tags: ['Admin Users'],
    )]
    #[OA\Response(response: 200, description: 'Lista de usuarios y catálogo de roles')]
    #[RequireScope('users.read')]
    #[Route('/admin/users', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $viewer = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($viewer === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        return $this->success($this->staffUserService->listForViewer($viewer));
    }

    #[OA\Post(
        path: '/api/v1/admin/users',
        summary: 'Crear usuario staff',
        description: 'Crea un nuevo usuario staff con los roles indicados.',
        tags: ['Admin Users'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'nuevo@ejemplo.com'),
                new OA\Property(property: 'password', type: 'string', example: 'miPassword123'),
                new OA\Property(property: 'name', type: 'string', example: 'Ana Gómez'),
                new OA\Property(property: 'roleIds', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'isActive', type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Usuario creado')]
    #[OA\Response(response: 400, description: 'Error de validación')]
    #[OA\Response(response: 409, description: 'Ya existe un usuario con ese email')]
    #[RequireScope('users.create')]
    #[Route('/admin/users', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $viewer = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($viewer === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        try {
            $dto = CreateStaffUserDto::fromJson($this->getJsonContent($request));
            $user = $this->staffUserService->create($viewer, $dto);

            return $this->success([
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
            ], 'Usuario creado', 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/admin/users/{id}',
        summary: 'Actualizar usuario staff',
        description: 'Actualiza parcialmente un usuario staff (solo los campos enviados).',
        tags: ['Admin Users'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Usuario actualizado')]
    #[OA\Response(response: 404, description: 'User not found')]
    #[RequireScope('users.update')]
    #[Route('/admin/users/{id}', name: 'api_admin_users_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $viewer = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($viewer === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        try {
            $user = $this->staffUserService->update($viewer, $id, $this->getJsonContent($request));

            return $this->success([
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
            ], 'Usuario actualizado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Delete(
        path: '/api/v1/admin/users/{id}',
        summary: 'Eliminar usuario staff',
        tags: ['Admin Users'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Usuario eliminado')]
    #[OA\Response(response: 404, description: 'User not found')]
    #[RequireScope('users.delete')]
    #[Route('/admin/users/{id}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $viewer = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($viewer === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        try {
            $this->staffUserService->delete($viewer, $id);

            return $this->success(null, 'Usuario eliminado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    #[OA\Put(
        path: '/api/v1/admin/users/{id}/status',
        summary: 'Activar/desactivar usuario staff',
        tags: ['Admin Users'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estado cambiado')]
    #[OA\Response(response: 404, description: 'User not found')]
    #[RequireScope('users.status')]
    #[Route('/admin/users/{id}/status', name: 'api_admin_users_toggle_status', methods: ['PUT'])]
    public function toggleStatus(string $id): JsonResponse
    {
        $viewer = $this->scopeAuthorizationService->getSelfServiceUser();
        if ($viewer === null) {
            return $this->error('Esta acción no aplica a este tipo de credencial', 400);
        }

        try {
            $user = $this->staffUserService->toggleStatus($viewer, $id);

            return $this->success(['isActive' => $user->isActive()], 'Estado cambiado');
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}

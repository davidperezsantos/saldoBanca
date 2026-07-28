<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Entity\Role;
use App\Security\Attribute\RequireScope;
use App\Services\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestión de roles y permisos desde mobile/ — espejo JSON de Controller/Admin/RoleController
 * (panel Twig, sesión de cookie, inalcanzable desde JWT). Misma lógica de negocio (incluido
 * convertToNested), sin capa de Service nueva — su par Twig tampoco la tiene.
 */
#[OA\Tag(name: 'Admin Roles', description: 'Gestión de roles y permisos (requiere scope roles.*)')]
class RoleController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionService $permissionService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/roles',
        summary: 'Listar roles y catálogo de permisos',
        description: 'Devuelve los roles con sus permisos aplanados y el catálogo completo de permisos asignables (para armar el picker).',
        tags: ['Admin Roles'],
    )]
    #[OA\Response(response: 200, description: 'Roles + catálogo de permisos')]
    #[RequireScope('roles.read')]
    #[Route('/admin/roles', name: 'api_admin_roles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $roles = $this->entityManager->getRepository(Role::class)->findBy([], ['name' => 'ASC']);

        $rolesData = array_map(function (Role $role) {
            $flat = $role->getFlatPermissions();
            return [
                'id' => (string) $role->getId(),
                'name' => $role->getName(),
                'label' => $role->getLabel(),
                'isSystem' => $role->isSystem(),
                'flatPermissions' => $flat,
                'permCount' => count($flat),
            ];
        }, $roles);

        return $this->success([
            'roles' => $rolesData,
            'permissionsCatalog' => $this->permissionService->getPermissionsForForm(),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/admin/roles',
        summary: 'Crear rol',
        tags: ['Admin Roles'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'label'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'cajero'),
                new OA\Property(property: 'label', type: 'string', example: 'Cajero'),
                new OA\Property(property: 'permissions', description: 'Claves planas, ej. "clients:view" o "exchange.providers:view"', type: 'array', items: new OA\Items(type: 'string')),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Rol creado')]
    #[OA\Response(response: 400, description: 'Nombre y etiqueta son requeridos')]
    #[RequireScope('roles.create')]
    #[Route('/admin/roles', name: 'api_admin_roles_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getJsonContent($request);
        $name = $data['name'] ?? '';
        $label = $data['label'] ?? '';
        $flatPermissions = $data['permissions'] ?? [];

        if (empty($name) || empty($label)) {
            return $this->error('Nombre y etiqueta son requeridos', 400);
        }

        $role = new Role();
        $role->setName($name);
        $role->setLabel($label);
        $role->setPermissions($this->convertToNested($flatPermissions));
        $role->setIsSystem(false);

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $this->success(['id' => (string) $role->getId()], 'Rol creado', 201);
    }

    #[OA\Put(
        path: '/api/v1/admin/roles/{id}',
        summary: 'Actualizar rol',
        tags: ['Admin Roles'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Rol actualizado')]
    #[OA\Response(response: 404, description: 'Role not found')]
    #[RequireScope('roles.update')]
    #[Route('/admin/roles/{id}', name: 'api_admin_roles_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $role = $this->entityManager->getRepository(Role::class)->find($id);
        if (!$role) {
            return $this->error('Role not found', 404);
        }

        $data = $this->getJsonContent($request);

        if (isset($data['label'])) {
            $role->setLabel($data['label']);
        }
        if (isset($data['permissions'])) {
            $role->setPermissions($this->convertToNested($data['permissions']));
        }

        $this->entityManager->flush();

        return $this->success(null, 'Rol actualizado');
    }

    #[OA\Delete(
        path: '/api/v1/admin/roles/{id}',
        summary: 'Eliminar rol',
        tags: ['Admin Roles'],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Rol eliminado')]
    #[OA\Response(response: 400, description: 'No se puede eliminar el rol del sistema')]
    #[OA\Response(response: 404, description: 'Role not found')]
    #[RequireScope('roles.delete')]
    #[Route('/admin/roles/{id}', name: 'api_admin_roles_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $role = $this->entityManager->getRepository(Role::class)->find($id);
        if (!$role) {
            return $this->error('Role not found', 404);
        }
        if ($role->isSystem()) {
            return $this->error('No se puede eliminar el rol del sistema', 400);
        }

        $this->entityManager->remove($role);
        $this->entityManager->flush();

        return $this->success(null, 'Rol eliminado');
    }

    private function convertToNested(array $flatPermissions): array
    {
        $nested = [];
        foreach ($flatPermissions as $perm) {
            if (str_contains($perm, '.')) {
                $parts = explode('.', $perm);
                $module = $parts[0];
                $rest = $parts[1];
                if (str_contains($rest, ':')) {
                    [$submodule, $action] = explode(':', $rest, 2);
                    $nested[$module][$submodule][] = $action;
                }
            } elseif (str_contains($perm, ':')) {
                [$module, $action] = explode(':', $perm, 2);
                $nested[$module][] = $action;
            }
        }
        return $nested;
    }
}

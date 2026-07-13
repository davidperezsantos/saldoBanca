<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Services\PermissionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RoleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PermissionService $permissionService
    ) {
    }

    #[Route('/admin/roles', name: 'admin_roles_page')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:view');

        $roles = $this->entityManager->getRepository(Role::class)->findAll();
        $allPermissions = $this->permissionService->getAllPermissions();

        $permLabels = [];
        foreach ($allPermissions as $perm) {
            $permLabels[$perm['key']] = $perm['label'];
        }

        $rolesData = [];
        foreach ($roles as $role) {
            $flat = [];
            $this->flattenPermissions($role->getPermissions(), '', $flat);
            $rolesData[] = [
                'role' => [
                    'id' => $role->getId()->toRfc4122(),
                    'name' => $role->getName(),
                    'label' => $role->getLabel(),
                    'isSystem' => $role->isSystem(),
                ],
                'flatPermissions' => $flat,
                'permCount' => count($flat),
            ];
        }

        return $this->render('admin/roles.html.twig', [
            'rolesData' => $rolesData,
            'permLabels' => $permLabels,
        ]);
    }

    private function flattenPermissions(array $perms, string $prefix, array &$result): void
    {
        foreach ($perms as $key => $value) {
            if (is_array($value)) {
                // Check if it's a list of actions (like ['view', 'create'])
                // or nested submodules (like ['users' => ['view', 'create']])
                $isListOfActions = !empty($value) && array_is_list($value);
                if ($isListOfActions) {
                    foreach ($value as $action) {
                        $result[] = $prefix . $key . ':' . $action;
                    }
                } else {
                    $this->flattenPermissions($value, $prefix . $key . '.', $result);
                }
            } else {
                $result[] = $prefix . $key . ':' . $value;
            }
        }
    }

    #[Route('/admin/roles/create', name: 'admin_role_create_page')]
    public function createPage(): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:create');

        $permissions = $this->permissionService->getPermissionsForForm();

        return $this->render('admin/role_form.html.twig', [
            'permissions' => $permissions,
            'role' => null,
        ]);
    }

    #[Route('/admin/roles/{id}/edit', name: 'admin_role_edit_page')]
    public function editPage(string $id): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:edit');

        $role = $this->entityManager->getRepository(Role::class)->find($id);

        if (!$role) {
            throw $this->createNotFoundException('Role not found');
        }

        $permissions = $this->permissionService->getPermissionsForForm();

        $flatPerms = [];
        $this->flattenPermissions($role->getPermissions(), '', $flatPerms);

        return $this->render('admin/role_form.html.twig', [
            'permissions' => $permissions,
            'role' => $role,
            'flatPermissions' => $flatPerms,
        ]);
    }

    #[Route('/admin/roles/store', name: 'admin_role_store', methods: ['POST'])]
    public function store(Request $request): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:create');

        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? '';
        $label = $data['label'] ?? '';
        $flatPermissions = $data['permissions'] ?? [];

        if (empty($name) || empty($label)) {
            return $this->json(['error' => 'Nombre y etiqueta son requeridos'], 400);
        }

        $nestedPermissions = $this->convertToNested($flatPermissions);

        $role = new Role();
        $role->setName($name);
        $role->setLabel($label);
        $role->setPermissions($nestedPermissions);
        $role->setIsSystem(false);

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'id' => $role->getId()]);
    }

    #[Route('/admin/roles/{id}/update', name: 'admin_role_update', methods: ['POST'])]
    public function update(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:edit');

        $role = $this->entityManager->getRepository(Role::class)->find($id);

        if (!$role) {
            return $this->json(['error' => 'Role not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $role->setLabel($data['label'] ?? $role->getLabel());

        if (isset($data['permissions'])) {
            $nestedPermissions = $this->convertToNested($data['permissions']);
            $role->setPermissions($nestedPermissions);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/roles/{id}/delete', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(string $id): Response
    {
        $this->denyAccessUnlessGranted('administration.roles:delete');

        $role = $this->entityManager->getRepository(Role::class)->find($id);

        if (!$role) {
            return $this->json(['error' => 'Role not found'], 404);
        }

        if ($role->isSystem()) {
            return $this->json(['error' => 'No se puede eliminar el rol del sistema'], 400);
        }

        $this->entityManager->remove($role);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
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
                    list($submodule, $action) = explode(':', $rest, 2);
                    $nested[$module][$submodule][] = $action;
                }
            } elseif (str_contains($perm, ':')) {
                list($module, $action) = explode(':', $perm, 2);
                $nested[$module][] = $action;
            }
        }
        return $nested;
    }
}

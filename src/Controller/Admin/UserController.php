<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\User;
use App\Entity\Role;
use App\Services\UsernameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsernameGenerator $usernameGenerator,
    ) {
    }

    #[Route('/admin/users', name: 'admin_users_page')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('administration.users:view');

        // El rol "de sistema" (isSystem, hoy solo super_admin) queda oculto y no asignable para
        // cualquiera que no sea a su vez de ese rol — un admin/soporte con permiso de gestión de
        // usuarios no debe poder ver ni crear otros super_admin.
        $viewerIsSystem = $this->getUser()?->getRole()?->isSystem() ?? false;

        $users = $this->entityManager->getRepository(User::class)->findAll();

        $usersData = [];
        foreach ($users as $user) {
            $role = $user->getRole();

            if (!$viewerIsSystem && $role?->isSystem()) {
                continue;
            }

            $usersData[] = [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'name' => $user->getName(),
                'isActive' => $user->isActive(),
                'lastLoginAt' => $user->getLastLoginAt() ? $user->getLastLoginAt()->format('Y-m-d H:i:s') : null,
                'role' => $role ? [
                    'id' => $role->getId()->toRfc4122(),
                    'name' => $role->getName(),
                    'label' => $role->getLabel(),
                ] : null,
            ];
        }

        $roles = $this->entityManager->getRepository(Role::class)->findBy([], ['name' => 'ASC']);
        $rolesData = array_values(array_map(fn(Role $role) => [
            'id' => $role->getId()->toRfc4122(),
            'label' => $role->getLabel(),
        ], array_filter($roles, fn(Role $role) => $viewerIsSystem || !$role->isSystem())));

        return $this->render('admin/users.html.twig', [
            'users' => $usersData,
            'roles' => $rolesData,
        ]);
    }

    #[Route('/admin/users/store', name: 'admin_user_store', methods: ['POST'])]
    public function store(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('administration.users:create');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        $password = $data['password'] ?? '';
        $roleId = $data['role_id'] ?? null;
        $isActive = $data['is_active'] ?? true;

        if (empty($email) || empty($password)) {
            return $this->json(['error' => 'Email y contraseña son requeridos'], 400);
        }

        $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['error' => 'Ya existe un usuario con ese email'], 400);
        }

        $role = null;
        if ($roleId) {
            $role = $this->entityManager->getRepository(Role::class)->find($roleId);
        }

        $viewerIsSystem = $this->getUser()?->getRole()?->isSystem() ?? false;
        if (!$viewerIsSystem && $role?->isSystem()) {
            return $this->json(['error' => 'No podés asignar ese rol'], 403);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setUsername($this->usernameGenerator->generate($name));
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRole($role);
        $user->setIsActive($isActive);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'id' => $user->getId()]);
    }

    #[Route('/admin/users/{id}/update', name: 'admin_user_update', methods: ['POST'])]
    public function update(string $id, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $this->denyAccessUnlessGranted('administration.users:edit');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            $existing = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existing) {
                return $this->json(['error' => 'Ya existe un usuario con ese email'], 400);
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (!empty($data['password'])) {
            $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        }

        if (isset($data['role_id'])) {
            $role = $data['role_id'] ? $this->entityManager->getRepository(Role::class)->find($data['role_id']) : null;

            $viewerIsSystem = $this->getUser()?->getRole()?->isSystem() ?? false;
            if (!$viewerIsSystem && $role?->isSystem()) {
                return $this->json(['error' => 'No podés asignar ese rol'], 403);
            }

            $user->setRole($role);
        }

        if (isset($data['is_active'])) {
            $user->setIsActive($data['is_active']);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(string $id): Response
    {
        $this->denyAccessUnlessGranted('administration.users:delete');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/admin/users/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(string $id): Response
    {
        $this->denyAccessUnlessGranted('administration.users:status');

        try {
            $this->validateCsrfToken();
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }

        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();

        return $this->json(['success' => true, 'is_active' => $user->isActive()]);
    }
}

<?php

namespace App\Services;

use App\DTO\Admin\CreateStaffUserDto;
use App\Entity\Role;
use App\Entity\User;
use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * CRUD de usuarios staff (super_admin/admin/support) para la API JSON que usa mobile/ —
 * espejo funcional de Controller/Admin/UserController.php (panel Twig), que se dejó tal cual
 * para no arriesgar el flujo ya andando; esta clase existe para no duplicar dos veces la regla
 * de "no podés ver/crear/tocar cuentas con un rol de sistema salvo que vos también lo tengas".
 */
class StaffUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UsernameGenerator $usernameGenerator,
    ) {
    }

    /**
     * @return array{users: list<array>, roles: list<array>}
     */
    public function listForViewer(User $viewer): array
    {
        $viewerIsSystem = $viewer->hasSystemRole();

        $users = [];
        foreach ($this->userRepository->findAll() as $user) {
            if (!$viewerIsSystem && $user->hasSystemRole()) {
                continue;
            }
            $users[] = $this->toArray($user);
        }

        $roles = [];
        foreach ($this->entityManager->getRepository(Role::class)->findBy([], ['name' => 'ASC']) as $role) {
            if (!$viewerIsSystem && $role->isSystem()) {
                continue;
            }
            $roles[] = $role->toSummaryArray();
        }

        return ['users' => $users, 'roles' => $roles];
    }

    public function create(User $viewer, CreateStaffUserDto $dto): User
    {
        if (empty($dto->email) || empty($dto->password)) {
            throw new ValidationException('Email y contraseña son requeridos');
        }

        if ($this->userRepository->findByEmail($dto->email)) {
            throw new BusinessException('Ya existe un usuario con ese email');
        }

        $roles = $this->resolveAssignableRoles($viewer, $dto->roleIds);

        $user = new User();
        $user->setEmail($dto->email);
        $user->setName($dto->name);
        $user->setUsername($this->usernameGenerator->generate($dto->name ?: $dto->email));
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->setAssignedRoles($roles);
        $user->setIsActive($dto->isActive);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function update(User $viewer, string $id, array $data): User
    {
        $user = $this->getVisibleOrFail($viewer, $id);

        if (isset($data['email']) && $data['email'] !== $user->getEmail()) {
            if ($this->userRepository->findByEmail($data['email'])) {
                throw new BusinessException('Ya existe un usuario con ese email');
            }
            $user->setEmail($data['email']);
        }

        if (isset($data['name'])) {
            $user->setName($data['name']);
        }

        if (!empty($data['password'])) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        }

        if (isset($data['roleIds'])) {
            $user->setAssignedRoles($this->resolveAssignableRoles($viewer, $data['roleIds']));
        }

        if (isset($data['isActive'])) {
            $user->setIsActive((bool) $data['isActive']);
        }

        $this->entityManager->flush();

        return $user;
    }

    public function delete(User $viewer, string $id): void
    {
        $user = $this->getVisibleOrFail($viewer, $id);
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    public function toggleStatus(User $viewer, string $id): User
    {
        $user = $this->getVisibleOrFail($viewer, $id);
        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();

        return $user;
    }

    private function getVisibleOrFail(User $viewer, string $id): User
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }
        if (!$viewer->hasSystemRole() && $user->hasSystemRole()) {
            throw new NotFoundException('User not found');
        }

        return $user;
    }

    /**
     * @param list<string> $roleIds
     * @return list<Role>
     */
    private function resolveAssignableRoles(User $viewer, array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $roles = $this->entityManager->getRepository(Role::class)->findBy(['id' => $roleIds]);

        if (!$viewer->hasSystemRole()) {
            foreach ($roles as $role) {
                if ($role->isSystem()) {
                    throw new BusinessException('No podés asignar ese rol');
                }
            }
        }

        return $roles;
    }

    private function toArray(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'name' => $user->getName(),
            'isActive' => $user->isActive(),
            'lastLoginAt' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'roles' => array_map(
                fn(Role $role) => $role->toSummaryArray(),
                $user->getAssignedRoles()->toArray()
            ),
        ];
    }
}

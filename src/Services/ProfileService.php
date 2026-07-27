<?php

namespace App\Services;

use App\DTO\ChangePasswordDto;
use App\DTO\ProfileDto;
use App\Entity\User;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Autoedición del propio perfil (bloque de usuario del sidebar, admin_layout.html.twig) — nombre
 * completo y contraseña propia. username/email quedan de solo lectura ahí a propósito: username
 * es el identificador de login (UsernameGenerator + unicidad), cambiarlo desde acá sin las mismas
 * validaciones rompería ese contrato.
 */
class ProfileService extends BaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct($entityManager);
    }

    /**
     * Si el usuario es dueño de una cuenta (cliente/negocio, User->account inverso de
     * Account->user), el teléfono se sincroniza también ahí — hasta ahora esa sincronización
     * solo pasaba en sentido cuenta -> usuario (ver AccountService::createAccount(),
     * AuthorizedService::updateAuthorized()), nunca al revés.
     */
    public function updateProfile(User $user, ProfileDto $dto): User
    {
        if ($dto->name === '') {
            throw new ValidationException('El nombre es requerido');
        }

        $user->setName($dto->name);

        if ($dto->phone !== null) {
            $user->setPhone($dto->phone);

            $account = $user->getAccount();
            if ($account !== null) {
                $account->setPhone($dto->phone);
            }
        }

        $this->flush();

        return $user;
    }

    /**
     * Mismo criterio que ProfileController (Api)::changePassword() — valida la actual antes de
     * dejar poner una nueva, mínimo 8 caracteres.
     */
    public function changePassword(User $user, ChangePasswordDto $dto): void
    {
        if (!$this->passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
            throw new ValidationException('La contraseña actual no es correcta');
        }
        if (strlen($dto->newPassword) < 8) {
            throw new ValidationException('La contraseña nueva debe tener al menos 8 caracteres');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->newPassword));
        $this->flush();
    }
}

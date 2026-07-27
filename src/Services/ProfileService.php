<?php

namespace App\Services;

use App\DTO\ProfileDto;
use App\Entity\User;
use App\Exception\ValidationException;

/**
 * Autoedición del propio perfil (bloque de usuario del sidebar, admin_layout.html.twig) — hoy solo
 * el nombre completo. username/email quedan de solo lectura ahí a propósito: username es el
 * identificador de login (UsernameGenerator + unicidad), cambiarlo desde acá sin las mismas
 * validaciones rompería ese contrato.
 */
class ProfileService extends BaseService
{
    public function updateName(User $user, ProfileDto $dto): User
    {
        if ($dto->name === '') {
            throw new ValidationException('El nombre es requerido');
        }

        $user->setName($dto->name);
        $this->flush();

        return $user;
    }
}

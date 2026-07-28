<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return preg_match('/^[a-z_]+\.[a-z_]+:[a-z_]+$/', $attribute)
            || preg_match('/^[a-z_]+:[a-z_]+$/', $attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Panel web (Twig): sin selector de "rol activo" como mobile — el permiso se concede si
        // ALGUNO de los roles asignados lo tiene (unión), no requiere elegir cuál usar.
        foreach ($user->getAssignedRoles() as $role) {
            $permissions = $role->getPermissions();

            if (str_contains($attribute, '.')) {
                [$module, $rest] = explode('.', $attribute, 2);
                [$submodule, $action] = explode(':', $rest, 2);

                if (isset($permissions[$module][$submodule]) && in_array($action, $permissions[$module][$submodule], true)) {
                    return true;
                }
                continue;
            }

            [$module, $action] = explode(':', $attribute, 2);

            if (isset($permissions[$module]) && in_array($action, $permissions[$module], true)) {
                return true;
            }
        }

        return false;
    }
}

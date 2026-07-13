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

        $role = $user->getRole();
        if (!$role) {
            return false;
        }

        $permissions = $role->getPermissions();

        if (str_contains($attribute, '.')) {
            [$module, $rest] = explode('.', $attribute, 2);
            [$submodule, $action] = explode(':', $rest, 2);

            return isset($permissions[$module][$submodule])
                && in_array($action, $permissions[$module][$submodule], true);
        }

        [$module, $action] = explode(':', $attribute, 2);

        return isset($permissions[$module])
            && in_array($action, $permissions[$module], true);
    }
}

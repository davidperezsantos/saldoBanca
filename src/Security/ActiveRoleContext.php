<?php

namespace App\Security;

/**
 * Holder simple con el "rol activo" del request actual — poblado por JWTActiveRoleListener a
 * partir del claim "activeRoleId" del JWT (ver AuthController::login/ProfileController::switchActiveRole,
 * donde se embebe). Existe porque un User puede tener varios Role a la vez y mobile elige con
 * cuál opera en cada sesión; el panel web (Twig, sesión de cookie) no usa esto — ver PermissionVoter,
 * que en cambio unifica todos los roles asignados.
 */
class ActiveRoleContext
{
    private ?string $roleId = null;

    public function getRoleId(): ?string
    {
        return $this->roleId;
    }

    public function setRoleId(?string $roleId): void
    {
        $this->roleId = $roleId;
    }
}

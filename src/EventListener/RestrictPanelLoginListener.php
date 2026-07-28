<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Los roles "cliente"/"emprendedor" (ver RoleSeedService) solo se asignan a través del
 * autorregistro público (/api/v1/register, /register/client) — su JWT es para otra API/app
 * propia del cliente, nunca para entrar al panel admin con sesión de cookie. Este listener
 * solo actúa sobre el FormLoginAuthenticator del firewall "main" (el /login del panel);
 * no afecta el login por JWT ni el OAuth2 de la API pública.
 */
#[AsEventListener(priority: -10)]
class RestrictPanelLoginListener
{
    public function __invoke(CheckPassportEvent $event): void
    {
        if (!$event->getAuthenticator() instanceof FormLoginAuthenticator) {
            return;
        }

        $user = $event->getPassport()->getUser();
        if (!$user instanceof User) {
            return;
        }

        $roles = $user->getAssignedRoles();
        if ($roles->isEmpty()) {
            return;
        }

        $hasPanelRole = false;
        foreach ($roles as $role) {
            if ($role->isAllowPanelLogin()) {
                $hasPanelRole = true;
                break;
            }
        }

        if (!$hasPanelRole) {
            throw new CustomUserMessageAuthenticationException('Esta cuenta no tiene acceso al panel de administración.');
        }
    }
}

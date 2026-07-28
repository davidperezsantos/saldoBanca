<?php

namespace App\EventListener;

use App\Security\ActiveRoleContext;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Extrae el claim "activeRoleId" (embebido por AuthController::login/ProfileController al crear
 * el JWT vía createFromPayload) y lo deja disponible en ActiveRoleContext para todo el resto del
 * request — ScopeAuthorizationService lo usa para saber, cuando el User tiene varios Role
 * asignados, con cuál de ellos calcular los scopes de esta sesión puntual.
 */
#[AsEventListener(event: Events::JWT_AUTHENTICATED)]
class JWTActiveRoleListener
{
    public function __construct(
        private ActiveRoleContext $activeRoleContext,
    ) {
    }

    public function __invoke(JWTAuthenticatedEvent $event): void
    {
        $roleId = $event->getPayload()['activeRoleId'] ?? null;
        $this->activeRoleContext->setRoleId(is_string($roleId) ? $roleId : null);
    }
}

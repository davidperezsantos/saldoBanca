<?php

namespace App\EventListener;

use App\Security\Attribute\RequireAnyScope;
use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Aplica #[RequireScope] antes de ejecutar cualquier controlador de /api/v1. Fail-closed a
 * propósito: una ruta de negocio (no pública) sin el atributo se bloquea (403) en vez de dejarse
 * pasar sin restricción de scope — preferible descubrir en pruebas que falta anotar un endpoint
 * nuevo, a dejarlo abierto por descuido y no enterarse.
 */
class RequireScopeListener
{
    private const SKIP_PREFIXES = [
        '/api/v1/login',
        '/api/v1/register',
        '/api/v1/password-reset',
        '/api/v1/webhooks',
    ];

    public function __construct(
        private ScopeAuthorizationService $scopeAuthorizationService,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, '/api/v1/')) {
            return;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $controller = $event->getController();
        if (is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflection = new \ReflectionMethod($controller, '__invoke');
        } else {
            return;
        }

        $anyAttributes = $reflection->getAttributes(RequireAnyScope::class);
        $allAttributes = $reflection->getAttributes(RequireScope::class);
        if (empty($anyAttributes) && empty($allAttributes)) {
            throw new \RuntimeException(sprintf(
                'API endpoint %s::%s is missing #[RequireScope]/#[RequireAnyScope] — refusing to serve it unprotected.',
                $reflection->getDeclaringClass()->getName(),
                $reflection->getName()
            ));
        }

        // Los endpoints /api/v1/admin/* son para staff (sesión JWT self-service) exclusivamente
        // — un cliente OAuth2 de negocio (client_credentials) nunca debe poder llamarlos, sin
        // importar qué scopes tenga guardados. No alcanza con que el scope pedido no esté en
        // config/packages/league_oauth2_server.yaml: si el cliente pide un token sin "scope"
        // explícito, el bundle de League le devuelve TODOS sus scopes guardados sin validarlos
        // contra ese catálogo (ver ScopeRepository::setupScopes() en el vendor), así que un
        // scope admin asignado por error a un cliente igual terminaría en un token válido. Este
        // chequeo es la única barrera real — se hace acá, una sola vez, para que ningún
        // controlador nuevo bajo Api/Admin/ pueda "olvidarse" de repetirlo.
        if (str_starts_with($path, '/api/v1/admin/') && $this->scopeAuthorizationService->getSelfServiceUser() === null) {
            throw new AccessDeniedException('Los endpoints de administración no son accesibles con credenciales de cliente OAuth2.');
        }

        if (!empty($anyAttributes)) {
            $this->scopeAuthorizationService->requireAny($anyAttributes[0]->newInstance()->scopes);
        } else {
            $this->scopeAuthorizationService->requireAll($allAttributes[0]->newInstance()->scopes);
        }
    }
}

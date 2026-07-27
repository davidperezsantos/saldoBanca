<?php

namespace App\EventListener;

use App\Security\Attribute\RequireScope;
use App\Security\ScopeAuthorizationService;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

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

        $attributes = $reflection->getAttributes(RequireScope::class);
        if (empty($attributes)) {
            throw new \RuntimeException(sprintf(
                'API endpoint %s::%s is missing #[RequireScope] — refusing to serve it unprotected.',
                $reflection->getDeclaringClass()->getName(),
                $reflection->getName()
            ));
        }

        $this->scopeAuthorizationService->requireAll($attributes[0]->newInstance()->scopes);
    }
}

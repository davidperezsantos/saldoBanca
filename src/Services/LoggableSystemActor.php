<?php

namespace App\Services;

use Gedmo\Loggable\LoggableListener;
use Gedmo\Tool\ActorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Fuerza un actor fijo para Gedmo\Loggable en flujos públicos sin token de seguridad real (login,
 * autorregistro, restablecer contraseña — todos bajo firewalls "security: false"/stateless). Sin
 * esto, LoggableListener::getUsername() devuelve null y revienta con
 * "LogEntry::setUsername(): ... must be of type string, null given" al hacer flush() sobre
 * cualquier entidad Loggable (ver App\Entity\User) — mismo problema que ya resolvíamos a mano en
 * comandos de consola (ver SeedRolesCommand).
 *
 * Solo usar en flujos que SIEMPRE corren sin usuario autenticado — si el llamador puede tener un
 * token real (ej. un admin logueado disparando la misma acción), no pisarlo con esto para no
 * perder el actor real en la auditoría.
 */
class LoggableSystemActor
{
    public function __construct(
        #[Autowire(service: 'stof_doctrine_extensions.listener.loggable')]
        private LoggableListener $loggableListener,
    ) {
    }

    public function actAsSystem(string $label = 'sistema'): void
    {
        $this->loggableListener->setActorProvider(new class($label) implements ActorProviderInterface {
            public function __construct(private string $label)
            {
            }

            public function getActor(): string
            {
                return $this->label;
            }
        });
    }
}

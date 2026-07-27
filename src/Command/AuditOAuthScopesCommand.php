<?php

namespace App\Command;

use App\Security\Attribute\RequireScope;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

/**
 * Defensa en profundidad: RequireScopeListener ya falla-cerrado en tiempo de ejecución si una
 * ruta de /api/v1 no pública no tiene #[RequireScope], pero este comando permite detectarlo antes
 * (en CI o localmente) sin necesidad de golpear cada endpoint a mano.
 */
#[AsCommand(
    name: 'app:oauth:audit-scopes',
    description: 'Verifica que toda ruta de /api/v1 (salvo las públicas) tenga #[RequireScope]'
)]
class AuditOAuthScopesCommand extends Command
{
    private const SKIP_PREFIXES = [
        '/api/v1/login',
        '/api/v1/register',
        '/api/v1/password-reset',
        '/api/v1/webhooks',
    ];

    public function __construct(
        private RouterInterface $router,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $unprotected = [];
        $covered = [];

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($path, '/api/v1/')) {
                continue;
            }

            foreach (self::SKIP_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            $controller = $route->getDefault('_controller');
            if (!is_string($controller) || !str_contains($controller, '::')) {
                continue;
            }

            [$class, $method] = explode('::', $controller);
            if (!class_exists($class) || !method_exists($class, $method)) {
                continue;
            }

            $reflection = new \ReflectionMethod($class, $method);
            $attributes = $reflection->getAttributes(RequireScope::class);

            if (empty($attributes)) {
                $unprotected[] = [$name, $path, $class . '::' . $method];
            } else {
                $covered[] = [$name, $path, implode(',', $attributes[0]->newInstance()->scopes)];
            }
        }

        $io->title('Auditoría de scopes OAuth2 en /api/v1');
        $io->table(['Ruta', 'Path', 'Scopes requeridos'], $covered);

        if (!empty($unprotected)) {
            $io->error(sprintf('%d ruta(s) de /api/v1 sin #[RequireScope]:', count($unprotected)));
            $io->table(['Ruta', 'Path', 'Controlador'], $unprotected);
            return Command::FAILURE;
        }

        $io->success(sprintf('Las %d rutas de /api/v1 (no públicas) tienen #[RequireScope].', count($covered)));
        return Command::SUCCESS;
    }
}

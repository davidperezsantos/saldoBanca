<?php

namespace App\Command;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Loggable\LoggableListener;
use Gedmo\Tool\ActorProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed-roles',
    description: 'Seed default roles with permissions'
)]
class SeedRolesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'stof_doctrine_extensions.listener.loggable')]
        private LoggableListener $loggableListener,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Gedmo\Loggable usa un ActorProvider (ligado al token de seguridad HTTP) que tiene
        // prioridad sobre setUsername() — en un comando de consola no hay token y ese provider
        // devuelve null, lo que revienta al persistir/actualizar un Role. Se reemplaza por uno fijo
        // mientras corre este comando (ver Gedmo\Loggable\LoggableListener::getUsername()).
        $this->loggableListener->setActorProvider(new class implements ActorProviderInterface {
            public function getActor(): string
            {
                return 'console';
            }
        });

        $io = new SymfonyStyle($input, $output);

        $roles = [
            [
                'name' => 'super_admin',
                'label' => 'Super Administrador',
                'isSystem' => true,
                'permissions' => [
                    'dashboard' => ['view'],
                    'administration' => ['users' => ['view', 'create', 'edit', 'delete', 'status'], 'roles' => ['view', 'create', 'edit', 'delete', 'assign_permissions']],
                    'oauth_clients' => ['view', 'create', 'edit', 'delete', 'status'],
                    'payment_gateway' => ['view', 'create', 'edit', 'delete', 'configure'],
                    'clients' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'balance'],
                    'businesses' => ['edit'],
                    'payout_accounts' => ['view', 'create', 'edit', 'delete'],
                    'authorized' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'verify'],
                    'exchange' => ['providers' => ['view', 'create', 'edit', 'delete'], 'rates' => ['view', 'fetch']],
                    'currencies' => ['view', 'create', 'edit', 'status'],
                    'recharges' => ['view', 'create', 'details', 'complete', 'fail', 'cancel'],
                    'transfers' => ['view', 'create', 'details', 'process', 'cancel', 'limits'],
                    'invoices' => ['view', 'create', 'details', 'pay', 'cancel', 'refund', 'summary'],
                    'reconciliations' => ['view', 'create', 'details', 'send', 'approve', 'settle'],
                    'reconciliation' => ['run', 'view'],
                    'commission_settlements' => ['view', 'create', 'details', 'approve', 'assign_account', 'settle', 'close'],
                    'history' => ['view', 'export'],
                ],
            ],
            [
                'name' => 'admin',
                'label' => 'Administrador',
                'isSystem' => false,
                'permissions' => [
                    'dashboard' => ['view'],
                    // A diferencia de super_admin, "admin" no puede gestionar roles (solo usuarios)
                    // ni ver/crear otros usuarios con un rol de sistema — eso lo filtra
                    // User::hasSystemRole() en Controller/Admin/UserController y
                    // Controller/Api/Admin/UserController, no este catálogo de permisos.
                    'administration' => ['users' => ['view', 'create', 'edit', 'delete', 'status']],
                    'clients' => ['view', 'create', 'edit', 'details', 'status', 'balance'],
                    'businesses' => ['edit'],
                    'payout_accounts' => ['view', 'create', 'edit', 'delete'],
                    'authorized' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'verify'],
                    'exchange' => ['providers' => ['view', 'create', 'edit'], 'rates' => ['view', 'fetch']],
                    'currencies' => ['view', 'create', 'edit', 'status'],
                    'recharges' => ['view', 'create', 'details', 'complete', 'fail', 'cancel'],
                    'transfers' => ['view', 'create', 'details', 'process', 'cancel', 'limits'],
                    'invoices' => ['view', 'create', 'details', 'pay', 'cancel', 'refund', 'summary'],
                    'reconciliations' => ['view', 'create', 'details', 'send', 'approve', 'settle'],
                    'reconciliation' => ['run', 'view'],
                    'commission_settlements' => ['view', 'create', 'details', 'approve', 'settle'],
                    'history' => ['view', 'export'],
                ],
            ],
            [
                'name' => 'support',
                'label' => 'Soporte',
                'isSystem' => false,
                'permissions' => [
                    'dashboard' => ['view'],
                    // Soporte puede ver la lista de usuarios staff y activar/desactivar cuentas
                    // (útil para desbloquear a alguien), pero no crear/editar/borrar usuarios ni
                    // tocar roles.
                    'administration' => ['users' => ['view', 'status']],
                    'clients' => ['view', 'details', 'status', 'balance'],
                    'payout_accounts' => ['view'],
                    'authorized' => ['view', 'details', 'status', 'verify'],
                    'recharges' => ['view', 'create', 'details'],
                    'transfers' => ['view', 'details'],
                    'invoices' => ['view', 'details', 'summary'],
                    'reconciliations' => ['view', 'details'],
                    'reconciliation' => ['view'],
                    'history' => ['view'],
                ],
            ],
        ];

        $repo = $this->entityManager->getRepository(Role::class);

        foreach ($roles as $roleData) {
            $role = $repo->findByName($roleData['name']);

            if (!$role) {
                $role = new Role();
                $role->setName($roleData['name']);
                $role->setIsSystem($roleData['isSystem']);
                $io->note('Creating role: ' . $roleData['name']);
            }

            $role->setLabel($roleData['label']);
            $role->setPermissions($roleData['permissions']);
            $this->entityManager->persist($role);
        }

        $this->entityManager->flush();

        $io->success('Roles seeded successfully');

        return Command::SUCCESS;
    }
}

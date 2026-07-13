<?php

namespace App\Command;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-roles',
    description: 'Seed default roles with permissions'
)]
class SeedRolesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $roles = [
            [
                'name' => 'super_admin',
                'label' => 'Super Administrador',
                'isSystem' => true,
                'permissions' => [
                    'dashboard' => ['view'],
                    'administration' => ['users' => ['view', 'create', 'edit', 'delete', 'status'], 'roles' => ['view', 'create', 'edit', 'delete', 'assign_permissions']],
                    'payment_gateway' => ['view', 'create', 'edit', 'delete', 'configure'],
                    'clients' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'balance'],
                    'businesses' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'balance'],
                    'authorized' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'verify', 'transfer'],
                    'exchange' => ['providers' => ['view', 'create', 'edit', 'delete'], 'rates' => ['view', 'fetch']],
                    'recharges' => ['view', 'create', 'details', 'complete', 'fail', 'cancel'],
                    'transfers' => ['view', 'create', 'details', 'process', 'cancel', 'limits'],
                    'invoices' => ['view', 'create', 'details', 'pay', 'cancel', 'refund', 'summary'],
                    'history' => ['view', 'export'],
                ],
            ],
            [
                'name' => 'admin',
                'label' => 'Administrador',
                'isSystem' => false,
                'permissions' => [
                    'dashboard' => ['view'],
                    'clients' => ['view', 'create', 'edit', 'details', 'status', 'balance'],
                    'businesses' => ['view', 'create', 'edit', 'details', 'status', 'balance'],
                    'authorized' => ['view', 'create', 'edit', 'delete', 'details', 'status', 'verify', 'transfer'],
                    'exchange' => ['providers' => ['view', 'create', 'edit'], 'rates' => ['view', 'fetch']],
                    'recharges' => ['view', 'create', 'details', 'complete', 'fail', 'cancel'],
                    'transfers' => ['view', 'create', 'details', 'process', 'cancel', 'limits'],
                    'invoices' => ['view', 'create', 'details', 'pay', 'cancel', 'refund', 'summary'],
                    'history' => ['view', 'export'],
                ],
            ],
            [
                'name' => 'support',
                'label' => 'Soporte',
                'isSystem' => false,
                'permissions' => [
                    'dashboard' => ['view'],
                    'clients' => ['view', 'details', 'status', 'balance'],
                    'businesses' => ['view', 'details', 'status', 'balance'],
                    'authorized' => ['view', 'details', 'status', 'verify', 'transfer'],
                    'recharges' => ['view', 'create', 'details'],
                    'transfers' => ['view', 'details'],
                    'invoices' => ['view', 'details', 'summary'],
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

<?php

namespace App\Services;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;

class RoleSeedService
{
    private const ROLES = [
        'cliente' => [
            'label' => 'Cliente',
            'permissions' => [
                'clients' => ['view', 'details', 'balance'],
                'recharges' => ['view', 'create', 'details'],
                'transfers' => ['view', 'create', 'details', 'limits'],
                'invoices' => ['view', 'details'],
                'history' => ['view'],
            ],
        ],
        'emprendedor' => [
            'label' => 'Emprendedor',
            'permissions' => [
                'clients' => ['view', 'create', 'edit', 'details', 'status', 'balance'],
                'businesses' => ['view', 'create', 'edit', 'details', 'status', 'balance'],
                'recharges' => ['view', 'create', 'details', 'complete', 'cancel'],
                'transfers' => ['view', 'create', 'details', 'process', 'cancel', 'limits'],
                'invoices' => ['view', 'create', 'details', 'pay', 'cancel', 'refund', 'summary'],
                'history' => ['view', 'export'],
                'authorized' => ['view', 'create', 'edit', 'details', 'status', 'verify'],
            ],
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function ensureRoleExists(string $name): Role
    {
        $repo = $this->entityManager->getRepository(Role::class);
        $role = $repo->findOneBy(['name' => $name]);

        if ($role) {
            return $role;
        }

        if (!isset(self::ROLES[$name])) {
            throw new \InvalidArgumentException("Unknown role: $name");
        }

        $config = self::ROLES[$name];

        $role = new Role();
        $role->setName($name);
        $role->setLabel($config['label']);
        $role->setPermissions($config['permissions']);
        $role->setIsSystem(false);

        $this->entityManager->persist($role);
        $this->entityManager->flush();

        return $role;
    }
}

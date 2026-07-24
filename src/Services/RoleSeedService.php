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
        private EntityManagerInterface $entityManager,
        private LoggableSystemActor $systemActor,
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
        // Roles que solo se asignan vía autorregistro público (ver RegistrationService) — su JWT
        // es para otra API/app propia del cliente, nunca deben poder loguearse en el panel admin.
        $role->setAllowPanelLogin(false);

        $this->entityManager->persist($role);
        // ensureRoleExists() se llama desde el autorregistro público (sin token de seguridad) la
        // primera vez que se necesita este rol — ver LoggableSystemActor.
        $this->systemActor->actAsSystem('autorregistro');
        $this->entityManager->flush();

        return $role;
    }
}

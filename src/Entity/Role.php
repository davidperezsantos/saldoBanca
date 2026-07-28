<?php

namespace App\Entity;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RoleRepository;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class Role extends BaseEntity
{
    #[ORM\Column(length: 50, unique: true)]
    #[Gedmo\Versioned]
    private string $name = '';

    #[ORM\Column(length: 100)]
    #[Gedmo\Versioned]
    private string $label = '';

    #[ORM\Column(type: 'json')]
    #[Gedmo\Versioned]
    private array $permissions = [];

    #[ORM\Column(type: 'boolean')]
    #[Gedmo\Versioned]
    private bool $isSystem = false;

    /**
     * Si este rol puede iniciar sesión en el panel admin (firewall "main", cookie de sesión).
     * En false para los roles que RoleSeedService asigna al autorregistro público (cliente,
     * emprendedor) — esos usuarios reciben un JWT pensado para otra API/app propia del cliente,
     * nunca para entrar a este panel de staff. Lo aplica RestrictPanelLoginSubscriber.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Gedmo\Versioned]
    private bool $allowPanelLogin = true;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function addPermission(string $module, string $action): static
    {
        if (!isset($this->permissions[$module])) {
            $this->permissions[$module] = [];
        }
        if (!in_array($action, $this->permissions[$module], true)) {
            $this->permissions[$module][] = $action;
        }
        return $this;
    }

    public function removePermission(string $module, string $action): static
    {
        if (isset($this->permissions[$module])) {
            $this->permissions[$module] = array_values(array_filter(
                $this->permissions[$module],
                fn(string $a) => $a !== $action
            ));
            if (empty($this->permissions[$module])) {
                unset($this->permissions[$module]);
            }
        }
        return $this;
    }

    public function hasPermission(string $module, string $action): bool
    {
        return isset($this->permissions[$module]) && in_array($action, $this->permissions[$module], true);
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;
        return $this;
    }

    public function isAllowPanelLogin(): bool
    {
        return $this->allowPanelLogin;
    }

    public function setAllowPanelLogin(bool $allowPanelLogin): static
    {
        $this->allowPanelLogin = $allowPanelLogin;
        return $this;
    }

    public function getSymfonyRole(): string
    {
        return 'ROLE_' . strtoupper($this->name);
    }

    /**
     * Resumen liviano (id/name/label) para exponer en respuestas JSON de la API — login, cambio
     * de rol activo, y el listado de usuarios staff — sin filtrar permisos ni metadata interna.
     *
     * @return array{id: string, name: string, label: string}
     */
    public function toSummaryArray(): array
    {
        return [
            'id' => (string) $this->getId(),
            'name' => $this->name,
            'label' => $this->label,
        ];
    }

    /**
     * Aplana los permisos anidados al mismo formato de clave que usa PermissionVoter
     * ("modulo:accion" o "modulo.submodulo:accion") — para exponerle al frontend (SPA Vue)
     * la lista exacta de acciones que puede hacer el usuario, y así ocultar botones de
     * acciones que el backend igual va a rechazar.
     *
     * @return list<string>
     */
    public function getFlatPermissions(): array
    {
        $flat = [];
        $this->flatten($this->permissions, '', $flat);
        return $flat;
    }

    private function flatten(array $perms, string $prefix, array &$result): void
    {
        foreach ($perms as $key => $value) {
            if (is_array($value)) {
                if (!empty($value) && array_is_list($value)) {
                    foreach ($value as $action) {
                        $result[] = $prefix . $key . ':' . $action;
                    }
                } else {
                    $this->flatten($value, $prefix . $key . '.', $result);
                }
            } else {
                $result[] = $prefix . $key . ':' . $value;
            }
        }
    }
}

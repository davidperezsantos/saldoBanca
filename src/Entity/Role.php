<?php

namespace App\Entity;

use App\Entity\Base\BaseEntity;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RoleRepository;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'roles')]
class Role extends BaseEntity
{
    #[ORM\Column(length: 50, unique: true)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $label = '';

    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isSystem = false;

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

    public function getSymfonyRole(): string
    {
        return 'ROLE_' . strtoupper($this->name);
    }
}

<?php

namespace App\Entity;

use App\Entity\Balance\Account;
use App\Entity\Base\BaseEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Auditada con Gedmo Loggable (ver App\Entity\LogEntry) — password/resetToken quedan fuera a
 * propósito de #[Gedmo\Versioned] para no dejar credenciales en la tabla de auditoría.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable(logEntryClass: LogEntry::class)]
class User extends BaseEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Column(length: 180, unique: true)]
    #[Gedmo\Versioned]
    private ?string $email = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $username = null;

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $name = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $phone = null;

    #[ORM\Column(type: 'boolean')]
    #[Gedmo\Versioned]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $resetToken = null;

    /**
     * Un User puede tener más de un Role a la vez (ej. admin + cliente) — ver
     * ScopeAuthorizationService/ActiveRoleContext para cómo mobile elige cuál está "activo" en
     * una sesión dada; el panel web (Twig) en cambio no tiene ese selector y opera con la unión
     * de permisos de todos los roles asignados (ver PermissionVoter).
     *
     * Sin #[Gedmo\Versioned]: Gedmo\Loggable no soporta versionar campos de colección (ManyToMany)
     * — antes, siendo ManyToOne, el cambio de rol sí quedaba en el log de auditoría; con el pase a
     * multi-rol se pierde ese detalle específico ahí (el resto de cambios del User se sigue
     * auditando igual).
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_roles')]
    private Collection $roles;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Account::class)]
    private ?Account $account = null;

    public function __construct()
    {
        parent::__construct();
        $this->roles = new ArrayCollection();
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->roles as $role) {
            $roles[] = $role->getSymfonyRole();
        }
        return array_unique($roles);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAssignedRoles(): Collection
    {
        return $this->roles;
    }

    public function addAssignedRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeAssignedRole(Role $role): static
    {
        $this->roles->removeElement($role);
        return $this;
    }

    /**
     * Reemplaza el set completo de roles asignados (usado por el alta/edición desde el panel
     * admin y por el autorregistro, que siempre asigna exactamente un rol).
     *
     * @param iterable<Role> $roles
     */
    public function setAssignedRoles(iterable $roles): static
    {
        $this->roles->clear();
        foreach ($roles as $role) {
            $this->addAssignedRole($role);
        }
        return $this;
    }

    /**
     * true si alguno de los roles asignados es "de sistema" (hoy solo super_admin) — se usa para
     * ocultar/proteger esas cuentas de viewers que no lo son (ver Controller/Admin/UserController
     * y Controller/Api/Admin/UserController).
     */
    public function hasSystemRole(): bool
    {
        foreach ($this->roles as $role) {
            if ($role->isSystem()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Unión de los permisos aplanados de todos los roles asignados — mismo criterio que
     * PermissionVoter (el panel web no tiene "rol activo", ve todo lo que le habilite cualquiera
     * de sus roles). La usa admin_layout.html.twig para armar window.__PERMISSIONS__, que el JS
     * del panel usa para mostrar/ocultar secciones del menú.
     *
     * @return list<string>
     */
    public function getFlatPermissions(): array
    {
        $flat = [];
        foreach ($this->roles as $role) {
            foreach ($role->getFlatPermissions() as $permission) {
                $flat[$permission] = true;
            }
        }
        return array_keys($flat);
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;
        return $this;
    }
}

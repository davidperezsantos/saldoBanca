<?php

namespace App\Security;

use App\Entity\Balance\Account;
use App\Entity\Role;
use App\Entity\User;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Chequeo programático de scopes OAuth2 del token actual — complemento al #[RequireScope]
 * declarativo, para cuando la lógica de negocio (no solo el endpoint entero) necesita ramificar
 * según qué scope trae el token.
 */
class ScopeAuthorizationService
{
    /**
     * Traduce los permisos de Role (formato "modulo:accion", ver Role::getFlatPermissions()) al
     * scope OAuth2 equivalente — mismo catálogo que ya usan los clientes OAuth2 de negocio, para
     * no inventar un sistema de permisos paralelo para la app móvil. Solo cubre los módulos que
     * tienen un scope de "mi propio recurso" equivalente; los módulos admin-only (administration,
     * oauth_clients, payment_gateway, currencies, reconciliations, commission_settlements) no
     * entran acá a propósito, un usuario final nunca actúa sobre esos recursos vía la app.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSION_TO_SCOPES = [
        'clients:view' => ['accounts.read'],
        'clients:details' => ['accounts.read'],
        'clients:create' => ['accounts.create'],
        'clients:edit' => ['accounts.update'],
        'clients:status' => ['accounts.status'],
        // No existe un permiso "clients:request_pin" separado en permissions.yaml todavía —
        // poder ver el saldo de la propia cuenta ya implica poder pedirle un PIN (que además
        // queda acotado a la cuenta propia por AccountController::requestPin() y rate-limited).
        // Si algún día se necesita separar ambos permisos, agregar la acción al catálogo.
        'clients:balance' => ['balance.read', 'accounts.request_pin'],
        'recharges:view' => ['recharges.read'],
        'recharges:details' => ['recharges.read'],
        'recharges:create' => ['recharges.create'],
        'recharges:complete' => ['recharges.complete'],
        'recharges:cancel' => ['recharges.cancel'],
        'recharges:fail' => ['recharges.fail'],
        'transfers:view' => ['transfers.read'],
        'transfers:details' => ['transfers.read'],
        'transfers:create' => ['transfers.create'],
        'transfers:process' => ['transfers.process'],
        'transfers:cancel' => ['transfers.cancel'],
        'invoices:view' => ['invoices.read'],
        'invoices:details' => ['invoices.read'],
        'invoices:create' => ['invoices.create'],
        'invoices:pay' => ['invoices.pay'],
        'invoices:cancel' => ['invoices.cancel'],
        'invoices:refund' => ['invoices.refund'],
        'history:view' => ['history.read'],
        'authorized:view' => ['authorized.read'],
        'authorized:details' => ['authorized.read'],
        'authorized:create' => ['authorized.create'],
        'authorized:edit' => ['authorized.update'],
        'authorized:delete' => ['authorized.delete'],
        'authorized:status' => ['authorized.status'],
    ];

    /**
     * De todos los scopes que PERMISSION_TO_SCOPES podría traducir, solo estos ya tienen filtro
     * de ownership en su controlador (ver AccountController::list, BalanceController::show/check
     * — "self" = mi propia Account). Otorgar cualquier otro scope hoy sería un IDOR: el rol
     * "cliente" ya tiene seedeado recharges/transfers/invoices (pensando en la app), pero esos
     * controladores todavía no filtran por dueño — agregarlos acá recién cuando tengan ese
     * filtro, junto con la pantalla correspondiente en mobile/.
     *
     * @var list<string>
     */
    private const SELF_SERVICE_SAFE_SCOPES = [
        'accounts.read',
        'accounts.request_pin',
        'authorized.read',
        'authorized.create',
        'authorized.update',
        'authorized.status',
        'balance.read',
        'recharges.read',
        'transfers.read',
        'transfers.create',
        'invoices.read',
        'invoices.pay',
        'history.read',
    ];

    /**
     * Traduce los permisos "administration.users:*" al scope equivalente que usa mobile/ para
     * que el staff (super_admin/admin/support) gestione otros Users desde la app — a diferencia
     * de PERMISSION_TO_SCOPES, estos scopes no son sobre "mi propio recurso" así que no pasan por
     * el filtro SELF_SERVICE_SAFE_SCOPES (ese filtro existe para evitar IDOR sobre la cuenta
     * propia, no aplica acá: la ownership de un User staff la resuelve StaffUserService con la
     * regla de isSystem, no con selfServiceOwnsAccount()).
     *
     * @var array<string, list<string>>
     */
    private const ADMIN_PERMISSION_TO_SCOPES = [
        'dashboard:view' => ['dashboard.read'],
        'administration.users:view' => ['users.read'],
        'administration.users:create' => ['users.create'],
        'administration.users:edit' => ['users.update'],
        'administration.users:delete' => ['users.delete'],
        'administration.users:status' => ['users.status'],
        'administration.roles:view' => ['roles.read'],
        'administration.roles:create' => ['roles.create'],
        'administration.roles:edit' => ['roles.update'],
        'administration.roles:delete' => ['roles.delete'],
        'oauth_clients:view' => ['oauth_clients.read'],
        'oauth_clients:create' => ['oauth_clients.create'],
        'oauth_clients:edit' => ['oauth_clients.update'],
        'oauth_clients:delete' => ['oauth_clients.delete'],
        'oauth_clients:status' => ['oauth_clients.status'],
        // Prefijo "_admin" a propósito: "clients:view"/"authorized:view"/etc. son las mismas
        // claves de permiso que ya traduce PERMISSION_TO_SCOPES para el rol "cliente" (su propia
        // cuenta) — nombrarlos distinto evita que un scope de "todo el sistema" se confunda con
        // el de "mi propio recurso" en el cliente (mobile ya los trata distinto, pero un nombre
        // compartido sería un accidente esperando pasar).
        'clients:view' => ['accounts_admin.read'],
        'clients:create' => ['accounts_admin.create'],
        'clients:edit' => ['accounts_admin.update'],
        'clients:status' => ['accounts_admin.status'],
        'clients:balance' => ['accounts_admin.balance'],
        'businesses:edit' => ['accounts_admin.approve_business'],
        'authorized:view' => ['authorized_admin.read'],
        'authorized:create' => ['authorized_admin.create'],
        'authorized:edit' => ['authorized_admin.update'],
        'authorized:delete' => ['authorized_admin.delete'],
        'authorized:status' => ['authorized_admin.status'],
        'authorized:verify' => ['authorized_admin.verify'],
        'exchange.providers:view' => ['exchange_providers_admin.read'],
        'exchange.providers:create' => ['exchange_providers_admin.create'],
        'exchange.providers:edit' => ['exchange_providers_admin.update'],
        'exchange.rates:view' => ['exchange_rates_admin.read'],
        'exchange.rates:fetch' => ['exchange_rates_admin.fetch'],
        'currencies:view' => ['currencies_admin.read'],
        'currencies:create' => ['currencies_admin.create'],
        'currencies:edit' => ['currencies_admin.update'],
        'currencies:status' => ['currencies_admin.status'],
    ];

    public function __construct(
        private Security $security,
        private ActiveRoleContext $activeRoleContext,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        $token = $this->security->getToken();
        if ($token instanceof OAuth2Token) {
            return $token->getScopes();
        }

        $selfServiceUser = $this->getSelfServiceUser();
        if ($selfServiceUser !== null) {
            return $this->getScopesForUser($selfServiceUser, $this->resolveActiveRole($selfServiceUser));
        }

        return [];
    }

    /**
     * De los roles asignados a $user, cuál está "activo" para el request actual. Con 0 o 1 rol no
     * hay ambigüedad. Con 2+ depende del claim "activeRoleId" del JWT (ver JWTActiveRoleListener)
     * — si no matchea ningún rol realmente asignado (token viejo tras revocar el rol, o el cliente
     * nunca lo mandó) se devuelve null a propósito: getScopesForUser() lo trata como "sin permisos
     * de rol", fail-closed, en vez de adivinar cuál usar.
     */
    private function resolveActiveRole(User $user): ?Role
    {
        $assignedRoles = $user->getAssignedRoles();

        if ($assignedRoles->count() <= 1) {
            return $assignedRoles->first() ?: null;
        }

        $roleId = $this->activeRoleContext->getRoleId();
        if ($roleId === null) {
            return null;
        }

        foreach ($assignedRoles as $role) {
            if ((string) $role->getId() === $roleId) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Scopes de $user para la sesión con $activeRole activo — independiente del token de
     * seguridad actual, para poder llamarse también desde AuthController::login()/
     * ProfileController::switchActiveRole() (donde todavía no hay un token autenticado contra el
     * firewall /api, se está por emitir uno recién).
     *
     * @return list<string>
     */
    public function getScopesForUser(User $user, ?Role $activeRole): array
    {
        $granted = [];

        if ($activeRole !== null) {
            $flatPermissions = $activeRole->getFlatPermissions();

            // Los scopes "de mi propio recurso" (accounts.read, balance.read, etc.) solo se
            // otorgan si el ROL ACTIVO es de los que nunca entran al panel (cliente/emprendedor,
            // allowPanelLogin=false) Y el usuario es dueño de una Account. No alcanza con mirar
            // solo la Account: un mismo User puede tener roles ["cliente", "admin"] — admin
            // también tiene permisos "clients:view"/"recharges:view" (para gestionar cuentas
            // AJENAS desde el panel), así que si solo mirásemos el nombre del permiso, entrar
            // como admin heredaría por error los scopes de "mi propia cuenta" y mobile mostraría
            // las pestañas de cliente (Recargas, Transferencias, etc.) estando en modo staff.
            if (!$activeRole->isAllowPanelLogin() && $user->getAccount() !== null) {
                $selfServiceScopes = [];
                foreach ($flatPermissions as $permission) {
                    foreach (self::PERMISSION_TO_SCOPES[$permission] ?? [] as $scope) {
                        $selfServiceScopes[$scope] = true;
                    }
                }
                $granted = array_intersect(array_keys($selfServiceScopes), self::SELF_SERVICE_SAFE_SCOPES);
            }

            // Espejo del guard de arriba, en sentido inverso: los scopes "de todo el sistema"
            // (accounts_admin.read, authorized_admin.read, etc.) solo se otorgan si el rol activo
            // es de staff (allowPanelLogin=true). "clients:view"/"authorized:view"/etc. son las
            // MISMAS claves de permiso que ya tiene el rol "cliente" con otro sentido (ver arriba)
            // — sin este guard, entrar con el rol "cliente" heredaría por error scopes para listar
            // TODAS las cuentas del sistema, no solo la propia.
            if ($activeRole->isAllowPanelLogin()) {
                foreach ($flatPermissions as $permission) {
                    foreach (self::ADMIN_PERMISSION_TO_SCOPES[$permission] ?? [] as $scope) {
                        $granted[] = $scope;
                    }
                }
            }
        }

        // Cambiar la propia contraseña no depende del Role ni expone datos de otra cuenta — se
        // otorga siempre a cualquier usuario final, sin importar el rol activo. No es un scope
        // OAuth2 real (no está en config/packages/league_oauth2_server.yaml a propósito): un
        // cliente OAuth2 de negocio nunca puede tenerlo, porque el servidor OAuth2 solo emite
        // scopes de ese catálogo.
        $granted[] = 'profile.update';

        return array_values(array_unique($granted));
    }

    /**
     * Usuario final autenticado por JWT (Lexik) contra el firewall /api — null si quien llama
     * es un cliente OAuth2 de negocio (OAuth2Token) o si no hay usuario autenticado.
     */
    public function getSelfServiceUser(): ?User
    {
        if ($this->security->getToken() instanceof OAuth2Token) {
            return null;
        }

        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * true si quien llama NO es self-service (cliente OAuth2 de negocio, sin restricción de
     * ownership acá) o si $account es justamente la cuenta propia del self-service user. Falso
     * en cualquier otro caso — usarlo para bloquear con 403 antes de devolver/mutar un recurso
     * de otra cuenta.
     */
    public function selfServiceOwnsAccount(?Account $account): bool
    {
        $user = $this->getSelfServiceUser();
        if ($user === null) {
            return true;
        }

        return $account !== null
            && (string) $user->getAccount()?->getId() === (string) $account->getId();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->getScopes(), true);
    }

    public function requireScope(string $scope): void
    {
        if (!$this->hasScope($scope)) {
            throw new AccessDeniedException("Missing required scope: {$scope}");
        }
    }

    /**
     * @param list<string> $scopes
     */
    public function requireAny(array $scopes): void
    {
        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return;
            }
        }

        throw new AccessDeniedException('Missing required scope (any of): ' . implode(', ', $scopes));
    }

    /**
     * @param list<string> $scopes
     */
    public function requireAll(array $scopes): void
    {
        $missing = array_diff($scopes, $this->getScopes());
        if (!empty($missing)) {
            throw new AccessDeniedException('Missing required scope(s): ' . implode(', ', $missing));
        }
    }
}

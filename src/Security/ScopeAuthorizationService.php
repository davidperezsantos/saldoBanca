<?php

namespace App\Security;

use App\Entity\Balance\Account;
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
        'balance.read',
        'recharges.read',
        'transfers.read',
        'transfers.create',
        'invoices.read',
        'invoices.pay',
        'history.read',
    ];

    public function __construct(
        private Security $security,
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
            return $this->getScopesForUser($selfServiceUser);
        }

        return [];
    }

    /**
     * Scopes self-service de $user según los permisos de su Role — independiente del token de
     * seguridad actual, para poder llamarse también desde AuthController::login() (donde todavía
     * no hay un token autenticado contra el firewall /api).
     *
     * @return list<string>
     */
    public function getScopesForUser(User $user): array
    {
        $role = $user->getRole();
        if ($role === null) {
            return [];
        }

        $scopes = [];
        foreach ($role->getFlatPermissions() as $permission) {
            foreach (self::PERMISSION_TO_SCOPES[$permission] ?? [] as $scope) {
                $scopes[$scope] = true;
            }
        }

        return array_values(array_intersect(array_keys($scopes), self::SELF_SERVICE_SAFE_SCOPES));
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

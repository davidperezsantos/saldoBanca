<?php

namespace App\Security;

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
     * Scopes que un usuario final autenticado por JWT (no un cliente OAuth2 de negocio) tiene
     * habilitados sobre SU PROPIO recurso — el filtro de ownership vive en cada controlador
     * (ver AccountController::list, BalanceController::show/check), esto solo decide qué
     * endpoints puede llegar a pisar. Acotado a propósito a lo que la app móvil necesita hoy;
     * sumar un scope aquí sin el chequeo de ownership correspondiente sería un IDOR.
     *
     * @var list<string>
     */
    private const SELF_SERVICE_SCOPES = ['accounts.read', 'balance.read'];

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

        if ($this->getSelfServiceUser() !== null) {
            return self::SELF_SERVICE_SCOPES;
        }

        return [];
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

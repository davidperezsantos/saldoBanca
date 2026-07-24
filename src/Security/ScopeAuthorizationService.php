<?php

namespace App\Security;

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
        if (!$token instanceof OAuth2Token) {
            return [];
        }

        return $token->getScopes();
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

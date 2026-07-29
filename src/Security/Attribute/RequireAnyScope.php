<?php

namespace App\Security\Attribute;

/**
 * Variante OR de #[RequireScope]: alcanza con tener UNO cualquiera de los scopes listados.
 * Existe para endpoints fusionados que sirven tanto a un caller self-service (scope
 * "recurso.accion") como a un caller admin (scope "recurso_admin.accion") sobre la misma ruta,
 * donde el propio método branchea el comportamiento con ScopeAuthorizationService::hasScope().
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireAnyScope
{
    /** @var list<string> */
    public readonly array $scopes;

    public function __construct(string ...$scopes)
    {
        $this->scopes = $scopes;
    }
}

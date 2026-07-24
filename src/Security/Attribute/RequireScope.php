<?php

namespace App\Security\Attribute;

/**
 * Declara qué scope(s) OAuth2 necesita un endpoint de /api/v1. Único mecanismo de autorización
 * por scope en el proyecto — no coexiste con el "oauth2_scopes" de ruta del bundle (que queda sin
 * usar a propósito: dos formas de declarar lo mismo es justo el tipo de inconsistencia que deja
 * pasar un endpoint sin proteger por descuido).
 *
 * Semántica siempre AND (todos los scopes listados son obligatorios) — es la opción más segura
 * por defecto; no se ofrece "cualquiera de estos" hasta que aparezca un caso de uso real que lo
 * necesite.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class RequireScope
{
    /** @var list<string> */
    public readonly array $scopes;

    public function __construct(string ...$scopes)
    {
        $this->scopes = $scopes;
    }
}

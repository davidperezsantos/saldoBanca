<?php

namespace App\EventListener;

use League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * El firewall "api" acepta tanto tokens OAuth2 de negocio como JWT de usuario final. Ninguno de
 * los dos authenticators inspecciona el formato del token en supports() — los dos reclaman
 * soporte para CUALQUIER header "Authorization: Bearer ..." — y cada uno devuelve una Response
 * (no null) en onAuthenticationFailure() por diseño propio. Symfony corta la cadena de
 * authenticators apenas el primero devuelve una Response de falla
 * (AuthenticatorManager::executeAuthenticators), así que sin este listener el segundo
 * authenticator nunca llega a intentarlo — ver hallazgo real en var/log/dev.log: "Token signature
 * mismatch" del OAuth2Authenticator con un JWT de Lexik perfectamente válido.
 *
 * Este listener "traga" la PRIMERA falla entre estos dos authenticators específicos para dejar
 * que el otro lo intente. Si el segundo también falla, esa sí es la respuesta real — ninguno de
 * los dos reconoció el token.
 */
#[AsEventListener]
class ApiDualAuthenticatorFallthroughListener
{
    private const RELEVANT_AUTHENTICATORS = [OAuth2Authenticator::class, JWTAuthenticator::class];

    public function __invoke(LoginFailureEvent $event): void
    {
        if ($event->getFirewallName() !== 'api') {
            return;
        }

        if (!in_array($event->getAuthenticator()::class, self::RELEVANT_AUTHENTICATORS, true)) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_dual_auth_fallthrough_used', false)) {
            return;
        }

        $request->attributes->set('_dual_auth_fallthrough_used', true);
        $event->setResponse(null);
    }
}

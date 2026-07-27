<?php

namespace App\Exception;

/**
 * Pasarela desconocida/inactiva o firma HMAC inválida — el llamador no está autorizado
 * a entregar este webhook, independientemente de si el payload en sí es válido.
 */
class WebhookAuthenticationException extends \RuntimeException
{
}

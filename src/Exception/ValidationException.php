<?php

namespace App\Exception;

/**
 * La petición en sí está mal formada o le falta un dato requerido (el caller puede corregirla
 * cambiando lo que envía) — distinto de BusinessException, donde el request es válido pero la
 * operación no procede por el estado actual del negocio.
 */
class ValidationException extends \InvalidArgumentException
{
}

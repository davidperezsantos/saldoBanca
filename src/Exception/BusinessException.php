<?php

namespace App\Exception;

/**
 * El request está bien formado pero la operación no puede completarse por el estado actual del
 * negocio (saldo insuficiente, estado inválido para la transición pedida, límite excedido, PIN
 * incorrecto, etc.) — distinto de ValidationException, donde el problema está en el request mismo.
 */
class BusinessException extends \RuntimeException
{
}

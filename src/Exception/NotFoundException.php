<?php

namespace App\Exception;

/**
 * El recurso referenciado por el request (por id, número, etc.) no existe.
 */
class NotFoundException extends \RuntimeException
{
}

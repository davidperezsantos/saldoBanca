<?php

namespace App\Http;

use App\Exception\BusinessException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null, ?string $errorType = null): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'errorType' => $errorType,
        ], $statusCode);
    }

    /**
     * Traduce una excepción de servicio a la respuesta HTTP correspondiente: ValidationException
     * (request mal formado) -> 422, NotFoundException (recurso inexistente) -> 404,
     * BusinessException (regla de negocio, ej. saldo insuficiente o estado inválido) -> 409.
     * Cualquier otra excepción no clasificada cae en el 400 genérico de siempre, para no romper
     * nada que todavía no se haya migrado a las excepciones tipadas.
     */
    public static function fromException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::error($e->getMessage(), 422, null, 'validation'),
            $e instanceof NotFoundException => self::error($e->getMessage(), 404, null, 'not_found'),
            $e instanceof BusinessException => self::error($e->getMessage(), 409, null, 'business'),
            default => self::error($e->getMessage(), 400),
        };
    }

    public static function paginated(array $data, int $total, int $page, int $limit): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }
}

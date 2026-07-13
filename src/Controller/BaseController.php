<?php

namespace App\Controller;

use App\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseController extends AbstractController
{
    protected function getJsonContent(Request $request): array
    {
        $content = $request->getContent();
        return json_decode($content, true) ?? [];
    }

    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $statusCode);
    }

    protected function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $statusCode, $errors);
    }

    protected function paginated(array $data, int $total, int $page, int $limit): JsonResponse
    {
        return ApiResponse::paginated($data, $total, $page, $limit);
    }

    protected function isAuthenticated(): bool
    {
        return $this->isGranted('IS_AUTHENTICATED_FULLY');
    }

    protected function checkApiKey(Request $request): bool
    {
        $apiKey = $request->headers->get('X-API-KEY');
        return $apiKey === ($_ENV['API_KEY_SISTEMA_EXTERNO'] ?? null);
    }
}

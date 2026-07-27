<?php

namespace App\Controller;

use App\Http\ApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

abstract class BaseController extends AbstractController
{
    private RequestStack $requestStack;
    private CsrfTokenManagerInterface $csrfManager;

    /**
     * Inyección por setter (no por constructor): la mayoría de los controladores que heredan de
     * BaseController definen su propio __construct() sin llamar a parent::__construct() (nunca
     * hizo falta antes — success()/error()/handleException()/etc. no dependen de nada inyectado
     * acá). Si estas dos dependencias fueran por constructor, validateCsrfToken() reventaría con
     * "must not be accessed before initialization" en cualquier controlador que no encadenara el
     * padre. #[Required] hace que Symfony las setee siempre después de construir el controlador,
     * sin importar qué __construct() tenga la clase hija.
     */
    #[Required]
    public function setBaseControllerDependencies(RequestStack $requestStack, CsrfTokenManagerInterface $csrfManager): void
    {
        $this->requestStack = $requestStack;
        $this->csrfManager = $csrfManager;
    }

    protected function getJsonContent(Request $request): array
    {
        $content = $request->getContent();
        return json_decode($content, true) ?? [];
    }

    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $statusCode);
    }

    protected function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null, ?string $errorType = null): JsonResponse
    {
        return ApiResponse::error($message, $statusCode, $errors, $errorType);
    }

    /**
     * Traduce una excepción de servicio a la respuesta HTTP correspondiente (ver
     * ApiResponse::fromException) — helper para controladores que ya extienden BaseController.
     */
    protected function handleException(\Throwable $e): JsonResponse
    {
        return ApiResponse::fromException($e);
    }

    protected function paginated(array $data, int $total, int $page, int $limit): JsonResponse
    {
        return ApiResponse::paginated($data, $total, $page, $limit);
    }

    protected function isAuthenticated(): bool
    {
        return $this->isGranted('IS_AUTHENTICATED_FULLY');
    }


    /**
     * Valida el token CSRF del header X-CSRF-TOKEN.
     * Lanza excepción si el token es inválido o falta.
     *
     * El id por defecto es 'ajax' porque es el mismo que genera
     * <meta name="csrf-token" content="{{ csrf_token('ajax') }}"> en admin_layout.html.twig y el
     * que común.ajax() reenvía en ese header — un id distinto acá nunca validaría (el HMAC del
     * token incluye el id), por eso las llamadas del panel admin fallarían siempre con un id
     * diferente a 'ajax'.
     *
     * @param string $tokenId Identificador del token (default: 'ajax')
     * @throws \RuntimeException
     */
    protected function validateCsrfToken(string $tokenId = 'ajax'): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \RuntimeException('No hay request disponible para validar CSRF');
        }

        $token = $request->headers->get('X-CSRF-TOKEN');
        if (!$token) {
            throw new \RuntimeException('Missing CSRF token');
        }

        if (!$this->csrfManager->isTokenValid(new CsrfToken($tokenId, $token))) {
            throw new \RuntimeException('Invalid CSRF token');
        }
    }
}

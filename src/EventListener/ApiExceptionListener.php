<?php

namespace App\EventListener;

use App\Http\ApiResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * /api/v1 es una API JSON: los rechazos de autenticación/autorización (token inválido, scope
 * insuficiente) no deben devolver la página HTML de error por defecto de Symfony, sino el mismo
 * contrato JSON que el resto de la API (ApiResponse::error()).
 *
 * Symfony lanza AccessDeniedException tanto para "sin token" como para "token válido pero sin
 * permiso" — hay que distinguirlos a mano (comprobando si hay un usuario autenticado) para no
 * devolver 403 en los dos casos y perder el 401 correcto de "no autenticado".
 *
 * También cubre las rutas `admin_*` que no son una página (no terminan en `_page`): son las
 * acciones JSON del panel Admin (crear/editar/eliminar/etc.), todas invocadas vía fetch() desde
 * Vue. Bug real encontrado: casi todos los controladores Admin llaman a
 * `denyAccessUnlessGranted()` ANTES de entrar al try/catch de la acción — cualquier
 * AccessDeniedException de un rol sin el permiso exacto escapaba sin atrapar y Symfony devolvía
 * su página HTML de error en vez de JSON (visto primero en `TransferController::create()`: el
 * fetch() del frontend recibía HTML donde esperaba JSON y la transferencia nunca se guardaba).
 * Deliberadamente NO se atrapa aquí cualquier \Throwable — solo lo relacionado a
 * autenticación/autorización, que es seguro de serializar; un 500 genuino en una ruta admin_* que
 * no sea de autorización debe seguir viendo la página de error normal de Symfony (en prod no
 * revela el mensaje interno de la excepción, cosa que si se hiciera igual que como aquí sí
 * ocurriría).
 */
class ApiExceptionListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route') ?? '';
        $isApi = str_starts_with($request->getPathInfo(), '/api/v1/');
        $isAdminJsonRoute = str_starts_with($route, 'admin_') && !str_ends_with($route, '_page');

        if (!$isApi && !$isAdminJsonRoute) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof AccessDeniedException) {
            if ($this->security->getUser() === null) {
                $event->setResponse(ApiResponse::error('Authentication required', 401));
                return;
            }

            $event->setResponse(ApiResponse::error($throwable->getMessage() ?: 'Access denied', 403));
            return;
        }

        if ($throwable instanceof AuthenticationException) {
            $event->setResponse(ApiResponse::error($throwable->getMessage() ?: 'Authentication required', 401));
        }
    }
}

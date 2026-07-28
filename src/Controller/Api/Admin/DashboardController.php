<?php

namespace App\Controller\Api\Admin;

use App\Controller\BaseController;
use App\Security\Attribute\RequireScope;
use App\Services\DashboardService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Estadísticas del sistema para mobile/ — espejo JSON de DashboardController (panel Twig).
 * DashboardService no tiene noción de "dueño" (son agregados de todo el sistema, no de una
 * Account puntual), así que a diferencia de los módulos de negocio (cuentas/recargas/etc.) esto
 * se puede exponer tal cual sin tocar ninguna lógica de ownership existente.
 */
#[OA\Tag(name: 'Admin Dashboard', description: 'Estadísticas del sistema (requiere scope dashboard.read)')]
class DashboardController extends BaseController
{
    public function __construct(
        private DashboardService $dashboardService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/admin/dashboard-stats',
        summary: 'Estadísticas del sistema',
        description: 'Saldos, cuentas, operaciones, actividad reciente y tasas de cambio — mismos datos que el dashboard del panel web.',
        tags: ['Admin Dashboard'],
    )]
    #[OA\Parameter(name: 'period', in: 'query', description: 'today, 7d o 30d (default 7d)', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Estadísticas del sistema')]
    #[RequireScope('dashboard.read')]
    #[Route('/admin/dashboard-stats', name: 'api_admin_dashboard_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $period = $request->query->get('period', '7d');

        return $this->success($this->dashboardService->getStats($period));
    }
}

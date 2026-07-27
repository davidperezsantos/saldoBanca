<?php

namespace App\Controller;

use App\Services\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {
    }

    #[Route('/', name: 'admin_dashboard_page')]
    public function dashboard(Request $request): Response
    {
        $this->denyAccessUnlessGranted('dashboard:view');

        $stats = $this->dashboardService->getStats($request->query->get('period', '7d'));

        return $this->render('dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

}

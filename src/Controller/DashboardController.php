<?php

namespace App\Controller;

use App\Services\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private DashboardService $dashboardService
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('dashboard:view');

        $stats = $this->dashboardService->getStats();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

}

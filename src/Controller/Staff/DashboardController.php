<?php

namespace App\Controller\Staff;

use App\Service\StaffMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/staff')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'staff_dashboard')]
    public function index(StaffMetricsService $staffMetricsService): Response
    {
        return $this->render('staff/dashboard/index.html.twig', [
            'summary' => $staffMetricsService->getQueueSummary(),
            'priority_orders' => $staffMetricsService->getPriorityOrders(4),
            'ready_to_ship_orders' => $staffMetricsService->getReadyToShipOrders(3),
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }
}

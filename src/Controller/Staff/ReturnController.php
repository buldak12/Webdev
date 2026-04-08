<?php

namespace App\Controller\Staff;

use App\Service\StaffMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/staff')]
class ReturnController extends AbstractController
{
    #[Route('/returns', name: 'staff_returns')]
    public function index(StaffMetricsService $staffMetricsService): Response
    {
        $refundedOrders = $staffMetricsService->getRefundedOrders(20);
        $totalRefunded = 0.0;

        foreach ($refundedOrders as $order) {
            $totalRefunded += (float) $order->getTotal();
        }

        return $this->render('staff/returns/index.html.twig', [
            'refunded_orders' => $refundedOrders,
            'returns_summary' => [
                'pending_review' => 0,
                'in_transit' => 0,
                'refunded_count' => count($refundedOrders),
                'total_refunded' => $totalRefunded,
            ],
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }
}

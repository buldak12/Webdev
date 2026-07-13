<?php

namespace App\Controller\Staff;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use App\Service\StaffMetricsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/staff/orders')]
class OrderController extends AbstractController
{
    #[Route('', name: 'staff_orders')]
    public function index(OrderRepository $orderRepository, StaffMetricsService $staffMetricsService): Response
    {
        return $this->render('staff/orders/index.html.twig', [
            'orders' => $orderRepository->findPendingFulfillment(50),
            'queue_summary' => $staffMetricsService->getQueueSummary(),
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }

    #[Route('/ready', name: 'staff_orders_ready')]
    public function ready(OrderRepository $orderRepository, StaffMetricsService $staffMetricsService): Response
    {
        $orders = $orderRepository->findReadyToShip(100);
        $courierBreakdown = [];

        foreach ($orders as $order) {
            $courierLabel = $order->getShipment()?->getCourierLabel() ?: 'Unassigned';
            $courierBreakdown[$courierLabel] = ($courierBreakdown[$courierLabel] ?? 0) + 1;
        }

        arsort($courierBreakdown);

        return $this->render('staff/orders/ready.html.twig', [
            'orders' => $orders,
            'courier_breakdown' => $courierBreakdown,
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }

    #[Route('/{id}/fulfill', name: 'staff_orders_fulfill')]
    public function fulfill(int $id, OrderRepository $orderRepository, StaffMetricsService $staffMetricsService): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('staff/orders/fulfill.html.twig', [
            'order' => $order,
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }

    #[Route('/{id}/start-processing', name: 'staff_orders_start_processing', methods: ['POST'])]
    public function startProcessing(int $id, Request $request, OrderRepository $orderRepository, OrderService $orderService): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if (!$this->isCsrfTokenValid('process-order-' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_orders_fulfill', ['id' => $id]);
        }

        try {
            $orderService->markAsProcessing($order);
            $this->addFlash('success', 'Order #' . $order->getOrderNumber() . ' is now being processed.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('staff_orders_fulfill', ['id' => $id]);
    }

    #[Route('/{id}/mark-ready', name: 'staff_orders_mark_ready', methods: ['POST'])]
    public function markReady(int $id, Request $request, OrderRepository $orderRepository, OrderService $orderService): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if (!$this->isCsrfTokenValid('ready-order-' . $order->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('staff_orders_fulfill', ['id' => $id]);
        }

        try {
            $orderService->markAsReadyToShip($order, $this->getUser());
            $this->addFlash('success', 'Order #' . $order->getOrderNumber() . ' is ready to ship!');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('staff_orders_ready');
    }

    #[Route('/{id}/ship', name: 'staff_orders_ship', methods: ['GET', 'POST'])]
    public function ship(int $id, Request $request, OrderRepository $orderRepository, OrderService $orderService, StaffMetricsService $staffMetricsService): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if ($order->getStatus() !== Order::STATUS_READY_TO_SHIP) {
            $this->addFlash('error', 'Only orders that are ready to ship can be shipped.');
            return $this->redirectToRoute('staff_orders_ready');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ship-order-' . $order->getId(), $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid CSRF token.');
                return $this->redirectToRoute('staff_orders_ship', ['id' => $id]);
            }

            $courier = $request->request->get('courier');
            $trackingNumber = trim($request->request->get('tracking_number', ''));

            if (empty($courier) || !isset(Shipment::COURIERS[$courier])) {
                $this->addFlash('error', 'Please select a valid courier.');
                return $this->redirectToRoute('staff_orders_ship', ['id' => $id]);
            }

            if (empty($trackingNumber)) {
                $this->addFlash('error', 'Please enter a tracking number.');
                return $this->redirectToRoute('staff_orders_ship', ['id' => $id]);
            }

            try {
                $orderService->markAsShipped($order, $courier, $trackingNumber);
                $this->addFlash('success', 'Order #' . $order->getOrderNumber() . ' has been shipped!');
                return $this->redirectToRoute('staff_orders_ready');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('staff/orders/ship.html.twig', [
            'order' => $order,
            'couriers' => Shipment::COURIERS,
            'staff_metrics' => $staffMetricsService->getNavigationMetrics(),
        ]);
    }
}

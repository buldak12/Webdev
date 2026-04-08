<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/orders')]
class OrderController extends AbstractController
{
    #[Route('', name: 'admin_orders')]
    public function index(OrderRepository $orderRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        
        if ($status) {
            $orders = $orderRepository->findByStatus($status);
        } else {
            $orders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 50);
        }

        return $this->render('admin/orders/index.html.twig', [
            'orders' => $orders,
            'current_status' => $status,
            'statuses' => Order::STATUSES,
        ]);
    }

    #[Route('/{id}', name: 'admin_orders_show', requirements: ['id' => '\d+'])]
    public function show(int $id, OrderRepository $orderRepository): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_orders_status', methods: ['POST'])]
    public function updateStatus(
        int $id,
        Request $request,
        OrderRepository $orderRepository,
        OrderService $orderService,
        EntityManagerInterface $em
    ): Response {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        if ($order->getStatus() === Order::STATUS_DELIVERED) {
            $this->addFlash('warning', 'Delivered orders are locked and cannot be edited.');
            return $this->redirectToRoute('admin_orders_show', ['id' => $id]);
        }

        $newStatus = $request->request->get('status');
        
        try {
            switch ($newStatus) {
                case Order::STATUS_PROCESSING:
                    $orderService->markAsProcessing($order);
                    break;
                case Order::STATUS_READY_TO_SHIP:
                    $orderService->markAsReadyToShip($order, $this->getUser());
                    break;
                case Order::STATUS_SHIPPED:
                    $courier = $request->request->get('courier');
                    $trackingNumber = $request->request->get('tracking_number');
                    $orderService->markAsShipped($order, $courier, $trackingNumber);
                    break;
                case Order::STATUS_DELIVERED:
                    $orderService->markAsDelivered($order);
                    break;
                case Order::STATUS_CANCELLED:
                    $reason = $request->request->get('reason');
                    $orderService->cancelOrder($order, $reason);
                    break;
                default:
                    $order->setStatus($newStatus);
                    $em->flush();
            }
            
            $this->addFlash('success', 'Order status updated');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_orders_show', ['id' => $id]);
    }

    #[Route('/{id}/refund', name: 'admin_orders_refund', methods: ['POST'])]
    public function refund(int $id, Request $request, OrderRepository $orderRepository, OrderService $orderService): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $reason = $request->request->get('reason');
        $result = $orderService->refundOrder($order, $reason);

        if ($result['success']) {
            $this->addFlash('success', 'Order refunded successfully');
        } else {
            $this->addFlash('error', $result['error']);
        }

        return $this->redirectToRoute('admin_orders_show', ['id' => $id]);
    }

    #[Route('/{id}/notes', name: 'admin_orders_notes', methods: ['POST'])]
    public function updateNotes(int $id, Request $request, OrderRepository $orderRepository, EntityManagerInterface $em): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $order->setInternalNotes($request->request->get('internal_notes'));
        $em->flush();

        $this->addFlash('success', 'Notes updated');
        return $this->redirectToRoute('admin_orders_show', ['id' => $id]);
    }
}

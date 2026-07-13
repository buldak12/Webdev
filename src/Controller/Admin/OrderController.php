<?php

namespace App\Controller\Admin;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    private function resolveRoute(Request $request, string $adminRoute, string $staffRoute): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');

        return str_starts_with($currentRoute, 'staff_sales_orders') ? $staffRoute : $adminRoute;
    }

    private function redirectAfterWrite(Request $request, string $adminRoute, string $staffRoute, array $params = []): Response
    {
        return $this->redirectToRoute($this->resolveRoute($request, $adminRoute, $staffRoute), $params);
    }

    #[Route('/admin/orders', name: 'admin_orders')]
    #[Route('/staff/sales/orders', name: 'staff_sales_orders')]
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
            'orders_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_sales_orders') ? 'staff_sales_orders' : 'admin_orders',
        ]);
    }

    #[Route('/admin/orders/new', name: 'admin_orders_new')]
    #[Route('/staff/sales/orders/new', name: 'staff_sales_orders_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ProductVariantRepository $variantRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $customerId = $request->request->get('user_id');
            $shippingAddressId = $request->request->get('shipping_address_id');
            $billingAddressId = $request->request->get('billing_address_id');
            $subtotal = (float) ($request->request->get('subtotal') ?? 0);
            $discount = (float) ($request->request->get('discount') ?? 0);
            $tax = (float) ($request->request->get('tax') ?? 0);
            $shippingCost = (float) ($request->request->get('shipping_cost') ?? 0);
            $notes = $request->request->get('notes');
            $internalNotes = $request->request->get('internal_notes');
            $items = $request->request->all('order_items') ?? [];

            // Validate inputs
            if (!$customerId || !$shippingAddressId || empty($items)) {
                $this->addFlash('error', 'Customer, shipping address, and at least one item are required.');
                return $this->redirectAfterWrite($request, 'admin_orders_new', 'staff_sales_orders_new');
            }

            $user = $em->getRepository(User::class)->find($customerId);
            $shippingAddress = $em->getRepository(Address::class)->find($shippingAddressId);
            $billingAddress = $billingAddressId ? $em->getRepository(Address::class)->find($billingAddressId) : $shippingAddress;

            if (!$user || !$shippingAddress) {
                $this->addFlash('error', 'Invalid customer or address selection.');
                return $this->redirectAfterWrite($request, 'admin_orders_new', 'staff_sales_orders_new');
            }

            // Create order
            $order = new Order();
            $order->setUser($user);
            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);
            $order->setSubtotal((string) $subtotal);
            $order->setDiscount((string) $discount);
            $order->setTax((string) $tax);
            $order->setShippingCost((string) $shippingCost);
            $order->setNotes($notes);
            $order->setInternalNotes($internalNotes);
            $order->setStatus(Order::STATUS_PENDING);

            // Add items
            foreach ($items as $itemData) {
                if (empty($itemData['variant_id']) || empty($itemData['quantity'])) {
                    continue;
                }

                $variant = $variantRepository->find($itemData['variant_id']);
                if (!$variant) {
                    continue;
                }

                $quantity = (int) $itemData['quantity'];
                $unitPrice = isset($itemData['unit_price']) && $itemData['unit_price'] 
                    ? (string) $itemData['unit_price']
                    : $variant->getPrice();

                $item = new OrderItem();
                $item->setVariant($variant);
                $item->setQuantity($quantity);
                $item->setUnitPrice($unitPrice);
                $item->setProductName($variant->getProduct()->getName());

                $order->addItem($item);
                $em->persist($item);
            }

            // Calculate total
            $order->calculateTotal();

            $em->persist($order);
            $em->flush();

            $this->addFlash('success', sprintf('Order #%s created successfully.', $order->getOrderNumber()));
            return $this->redirectAfterWrite($request, 'admin_orders_show', 'staff_sales_orders_show', ['id' => $order->getId()]);
        }

        return $this->render('admin/orders/new.html.twig', [
            'orders_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_sales_orders') ? 'staff_sales_orders' : 'admin_orders',
        ]);
    }

    #[Route('/admin/orders/{id}', name: 'admin_orders_show', requirements: ['id' => '\d+'])]
    #[Route('/staff/sales/orders/{id}', name: 'staff_sales_orders_show', requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, OrderRepository $orderRepository): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('admin/orders/show.html.twig', [
            'order' => $order,
            'orders_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_sales_orders') ? 'staff_sales_orders' : 'admin_orders',
        ]);
    }

    #[Route('/admin/orders/{id}/status', name: 'admin_orders_status', methods: ['POST'])]
    #[Route('/staff/sales/orders/{id}/status', name: 'staff_sales_orders_status', methods: ['POST'])]
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
            return $this->redirectAfterWrite($request, 'admin_orders_show', 'staff_sales_orders_show', ['id' => $id]);
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

        return $this->redirectAfterWrite($request, 'admin_orders_show', 'staff_sales_orders_show', ['id' => $id]);
    }

    #[Route('/admin/orders/{id}/refund', name: 'admin_orders_refund', methods: ['POST'])]
    #[Route('/staff/sales/orders/{id}/refund', name: 'staff_sales_orders_refund', methods: ['POST'])]
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

        return $this->redirectAfterWrite($request, 'admin_orders_show', 'staff_sales_orders_show', ['id' => $id]);
    }

    #[Route('/admin/orders/{id}/notes', name: 'admin_orders_notes', methods: ['POST'])]
    #[Route('/staff/sales/orders/{id}/notes', name: 'staff_sales_orders_notes', methods: ['POST'])]
    public function updateNotes(int $id, Request $request, OrderRepository $orderRepository, EntityManagerInterface $em): Response
    {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        $order->setInternalNotes($request->request->get('internal_notes'));
        $em->flush();

        $this->addFlash('success', 'Notes updated');
        return $this->redirectAfterWrite($request, 'admin_orders_show', 'staff_sales_orders_show', ['id' => $id]);
    }

    #[Route('/admin/orders/{id}/delete', name: 'admin_orders_delete', methods: ['POST'])]
    #[Route('/staff/sales/orders/{id}/delete', name: 'staff_sales_orders_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        OrderRepository $orderRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $em
    ): Response {
        $order = $orderRepository->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Order not found');
        }

        foreach ($paymentRepository->findBy(['order' => $order]) as $payment) {
            $em->remove($payment);
        }

        $em->remove($order);
        $em->flush();

        $this->addFlash('success', 'Order deleted successfully.');
        return $this->redirectAfterWrite($request, 'admin_orders', 'staff_sales_orders');
    }

    // ─── Mobile Orders ────────────────────────────────────────────────────────

    #[Route('/admin/mobile-orders', name: 'admin_mobile_orders')]
    public function mobileOrders(EntityManagerInterface $em): Response
    {
        $orders = [];
        try {
            $conn = $em->getConnection();
            $tableExists = $conn->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mobile_orders'"
            );
            if ($tableExists) {
                $orders = $conn->fetchAllAssociative(
                    'SELECT * FROM mobile_orders ORDER BY created_at DESC LIMIT 100'
                );
                foreach ($orders as &$o) {
                    $o['items'] = json_decode($o['items_json'], true) ?? [];
                    $o['order_number'] = 'MOB-' . str_pad((string) $o['id'], 6, '0', STR_PAD_LEFT);
                }
                unset($o);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Could not load mobile orders: ' . $e->getMessage());
        }

        return $this->render('admin/orders/mobile.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/mobile-orders/{id}/status', name: 'admin_mobile_orders_status', methods: ['POST'])]
    public function updateMobileOrderStatus(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $newStatus = $request->request->get('status', 'pending');
        $allowed = ['pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            $this->addFlash('error', 'Invalid status.');
            return $this->redirectToRoute('admin_mobile_orders');
        }

        try {
            $conn = $em->getConnection();
            $conn->update('mobile_orders', ['status' => $newStatus], ['id' => $id]);
            $this->addFlash('success', 'Mobile order #' . $id . ' status updated to ' . $newStatus);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to update status: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_mobile_orders');
    }
}

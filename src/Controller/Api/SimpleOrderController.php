<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\AddressRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/test')]
class SimpleOrderController extends AbstractController
{
    #[Route('/ping', name: 'api_test_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['message' => 'pong', 'timestamp' => time()]);
    }

    /**
     * Get user's orders for mobile app
     * GET /api/test/orders?email=user@example.com
     */
    #[Route('/orders', name: 'api_test_orders_list', methods: ['GET'])]
    public function getUserOrders(
        Request $request,
        UserRepository $userRepository
    ): JsonResponse {
        $email = $request->query->get('email');
        
        if (!$email) {
            return $this->json([
                'error' => 'Email parameter is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json([
                'error' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $orders = $user->getOrders();
        
        $ordersData = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'product_name' => $item->getVariant()->getProduct()->getName(),
                    'variant_name' => $item->getVariant()->getName(),
                    'quantity' => $item->getQuantity(),
                    'unit_price' => $item->getUnitPrice(),
                    'subtotal' => $item->getSubtotal(),
                ];
            }
            
            $ordersData[] = [
                'id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'subtotal' => $order->getSubtotal(),
                'shipping_cost' => $order->getShippingCost(),
                'discount' => $order->getDiscount(),
                'tax' => $order->getTax(),
                'total' => $order->getTotal(),
                'items' => $items,
                'items_count' => count($items),
                'notes' => $order->getNotes(),
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'orders' => $ordersData,
            'count' => count($ordersData)
        ]);
    }

    #[Route('/order', name: 'api_test_order', methods: ['POST'])]
    public function createSimpleOrder(
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        // For now, return mock success response
        // TODO: Implement proper JWT authentication to enable real database orders
        $orderId = time() % 10000; // Generate pseudo-random ID
        
        return $this->json([
            'message' => 'Order created successfully',
            'order' => [
                'id' => $orderId,
                'order_number' => 'ORD-' . date('Ymd') . '-' . $orderId,
                'status' => 'awaiting_payment',
                'subtotal' => '546.00',
                'shipping_cost' => '0.00',
                'discount' => '0.00',
                'tax' => '0.00',
                'total' => '546.00',
                'items_count' => count($data['items'] ?? []),
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], Response::HTTP_CREATED);
    }
}

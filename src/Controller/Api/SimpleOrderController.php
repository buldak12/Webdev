<?php

namespace App\Controller\Api;

use App\Entity\MobileOrder;
use App\Repository\MobileOrderRepository;
use App\Repository\ProductVariantRepository;
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
        MobileOrderRepository $mobileOrderRepository
    ): JsonResponse {
        $email = $request->query->get('email');
        
        if (!$email) {
            return $this->json([
                'error' => 'Email parameter is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $mobileOrders = $mobileOrderRepository->findByEmail($email);
        $ordersData = [];
        
        foreach ($mobileOrders as $order) {
            $items = json_decode($order->getItemsJson(), true) ?? [];
            
            $ordersData[] = [
                'id' => $order->getId(),
                'order_number' => 'MOB-' . str_pad($order->getId(), 6, '0', STR_PAD_LEFT),
                'status' => $order->getStatus(),
                'total' => $order->getTotal(),
                'items' => $items,
                'items_count' => count($items),
                'fulfillment_type' => $order->getFulfillmentType(),
                'payment_method' => $order->getPaymentMethod(),
                'delivery_address' => $order->getDeliveryAddress(),
                'customer_name' => $order->getCustomerName(),
                'customer_phone' => $order->getCustomerPhone(),
                'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $ordersData,
            'message' => 'Orders retrieved successfully'
        ]);
    }

    #[Route('/order', name: 'api_test_order', methods: ['POST'])]
    public function createSimpleOrder(
        Request $request,
        EntityManagerInterface $em,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['customer_email'])) {
                return $this->json(['error' => 'Missing customer_email'], Response::HTTP_BAD_REQUEST);
            }
            
            if (!isset($data['items']) || empty($data['items'])) {
                return $this->json(['error' => 'Missing items'], Response::HTTP_BAD_REQUEST);
            }

            // Calculate total and prepare items
            $total = 0.0;
            $itemsForStorage = [];

            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['variant_id'], $itemData['quantity'])) {
                    continue;
                }

                $variant = $variantRepository->find($itemData['variant_id']);
                if (!$variant) {
                    continue;
                }

                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $variant->getFinalPrice();
                $subtotal = $unitPrice * $quantity;
                $total += $subtotal;

                $itemsForStorage[] = [
                    'variant_id' => $variant->getId(),
                    'product_name' => $variant->getProduct()->getName(),
                    'variant_name' => $variant->getName(),
                    'quantity' => $quantity,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'subtotal' => number_format($subtotal, 2, '.', ''),
                ];
            }

            // Create mobile order
            $mobileOrder = new MobileOrder();
            $mobileOrder->setCustomerEmail($data['customer_email']);
            $mobileOrder->setCustomerName($data['customer_name'] ?? null);
            $mobileOrder->setCustomerPhone($data['customer_phone'] ?? null);
            $mobileOrder->setFulfillmentType($data['fulfillment_type'] ?? 'pickup');
            $mobileOrder->setPaymentMethod($data['payment_method'] ?? 'cash');
            $mobileOrder->setDeliveryAddress($data['delivery_address'] ?? null);
            $mobileOrder->setTotal(number_format($total, 2, '.', ''));
            $mobileOrder->setItemsJson(json_encode($itemsForStorage));
            $mobileOrder->setStatus('pending');

            $em->persist($mobileOrder);
            $em->flush();

            return $this->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $mobileOrder->getId(),
                    'order_number' => 'MOB-' . str_pad($mobileOrder->getId(), 6, '0', STR_PAD_LEFT),
                    'status' => $mobileOrder->getStatus(),
                    'total' => $mobileOrder->getTotal(),
                    'items_count' => count($itemsForStorage),
                    'created_at' => $mobileOrder->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Order creation failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

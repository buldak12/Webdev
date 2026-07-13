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
        EntityManagerInterface $em
    ): JsonResponse {
        $email = $request->query->get('email');
        
        if (!$email) {
            return $this->json([
                'error' => 'Email parameter is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $conn = $em->getConnection();
            
            // Check if table exists first
            $tableExists = $conn->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mobile_orders'"
            );
            
            if (!$tableExists) {
                return $this->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No orders found'
                ]);
            }
            
            $orders = $conn->fetchAllAssociative(
                'SELECT * FROM mobile_orders WHERE customer_email = ? ORDER BY created_at DESC',
                [$email]
            );
            
            $ordersData = [];
            foreach ($orders as $order) {
                $items = json_decode($order['items_json'], true) ?? [];
                
                $ordersData[] = [
                    'id' => (int) $order['id'],
                    'order_number' => 'MOB-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT),
                    'status' => $order['status'],
                    'total' => $order['total'],
                    'items' => $items,
                    'items_count' => count($items),
                    'fulfillment_type' => $order['fulfillment_type'],
                    'payment_method' => $order['payment_method'],
                    'delivery_address' => $order['delivery_address'],
                    'customer_name' => $order['customer_name'],
                    'customer_phone' => $order['customer_phone'],
                    'created_at' => $order['created_at'],
                ];
            }

            return $this->json([
                'success' => true,
                'data' => $ordersData,
                'message' => 'Orders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => true,
                'data' => [],
                'message' => 'No orders found',
                'error' => $e->getMessage()
            ]);
        }
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

            // Calculate total and prepare items using direct SQL to avoid entity issues
            $total = 0.0;
            $itemsForStorage = [];

            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['variant_id'], $itemData['quantity'])) {
                    continue;
                }

                try {
                    $variant = $variantRepository->find($itemData['variant_id']);
                    if (!$variant) {
                        return $this->json([
                            'error' => 'Variant not found',
                            'variant_id' => $itemData['variant_id']
                        ], Response::HTTP_BAD_REQUEST);
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
                } catch (\Exception $variantError) {
                    return $this->json([
                        'error' => 'Error loading variant',
                        'variant_id' => $itemData['variant_id'],
                        'message' => $variantError->getMessage()
                    ], Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            // Insert using raw SQL to avoid entity/migration issues
            try {
                $conn = $em->getConnection();
                
                // Check if table exists
                $tableExists = $conn->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mobile_orders'"
                );
                
                if (!$tableExists) {
                    // Create table if it doesn't exist
                    $conn->executeStatement("
                        CREATE TABLE IF NOT EXISTS mobile_orders (
                            id INT AUTO_INCREMENT NOT NULL,
                            customer_email VARCHAR(255) NOT NULL,
                            customer_name VARCHAR(255) DEFAULT NULL,
                            customer_phone VARCHAR(50) DEFAULT NULL,
                            items_json LONGTEXT NOT NULL,
                            status VARCHAR(20) NOT NULL,
                            fulfillment_type VARCHAR(20) DEFAULT NULL,
                            payment_method VARCHAR(50) DEFAULT NULL,
                            delivery_address VARCHAR(255) DEFAULT NULL,
                            total NUMERIC(10, 2) NOT NULL,
                            created_at DATETIME NOT NULL,
                            INDEX idx_customer_email (customer_email),
                            INDEX idx_created_at (created_at),
                            PRIMARY KEY(id)
                        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
                    ");
                }
                
                // Insert order
                $conn->insert('mobile_orders', [
                    'customer_email' => $data['customer_email'],
                    'customer_name' => $data['customer_name'] ?? null,
                    'customer_phone' => $data['customer_phone'] ?? null,
                    'items_json' => json_encode($itemsForStorage),
                    'status' => 'pending',
                    'fulfillment_type' => $data['fulfillment_type'] ?? 'pickup',
                    'payment_method' => $data['payment_method'] ?? 'cash',
                    'delivery_address' => $data['delivery_address'] ?? null,
                    'total' => number_format($total, 2, '.', ''),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                ]);
                
                $orderId = $conn->lastInsertId();

                return $this->json([
                    'message' => 'Order created successfully',
                    'order' => [
                        'id' => (int) $orderId,
                        'order_number' => 'MOB-' . str_pad($orderId, 6, '0', STR_PAD_LEFT),
                        'status' => 'pending',
                        'total' => number_format($total, 2, '.', ''),
                        'items_count' => count($itemsForStorage),
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ], Response::HTTP_CREATED);
            } catch (\Exception $dbError) {
                return $this->json([
                    'error' => 'Database error',
                    'message' => $dbError->getMessage(),
                    'code' => $dbError->getCode()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Order creation failed',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

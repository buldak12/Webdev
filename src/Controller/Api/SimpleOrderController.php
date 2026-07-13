<?php

namespace App\Controller\Api;

use App\Repository\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test')]
class SimpleOrderController extends AbstractController
{
    #[Route('/ping', name: 'api_test_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return $this->json(['message' => 'pong', 'timestamp' => time()]);
    }

    /**
     * GET /api/test/orders?email=user@example.com
     */
    #[Route('/orders', name: 'api_test_orders_list', methods: ['GET'])]
    public function getUserOrders(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $email = $request->query->get('email');

        if (!$email) {
            return $this->json(['error' => 'Email parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $conn = $em->getConnection();

            // Create table if needed
            $conn->executeStatement("
                CREATE TABLE IF NOT EXISTS mobile_orders (
                    id INT AUTO_INCREMENT NOT NULL,
                    customer_email VARCHAR(255) NOT NULL,
                    customer_name VARCHAR(255) DEFAULT NULL,
                    customer_phone VARCHAR(50) DEFAULT NULL,
                    items_json LONGTEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    fulfillment_type VARCHAR(20) DEFAULT NULL,
                    payment_method VARCHAR(50) DEFAULT NULL,
                    delivery_address VARCHAR(255) DEFAULT NULL,
                    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            ");

            $orders = $conn->fetchAllAssociative(
                'SELECT * FROM mobile_orders WHERE customer_email = ? ORDER BY created_at DESC',
                [$email]
            );

            $ordersData = [];
            foreach ($orders as $order) {
                $items = json_decode($order['items_json'], true) ?? [];
                $ordersData[] = [
                    'id'               => (int) $order['id'],
                    'order_number'     => 'MOB-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT),
                    'status'           => $order['status'],
                    'total'            => $order['total'],
                    'items'            => $items,
                    'items_count'      => count($items),
                    'fulfillment_type' => $order['fulfillment_type'],
                    'payment_method'   => $order['payment_method'],
                    'delivery_address' => $order['delivery_address'],
                    'customer_name'    => $order['customer_name'],
                    'customer_phone'   => $order['customer_phone'],
                    'created_at'       => $order['created_at'],
                ];
            }

            return $this->json(['success' => true, 'data' => $ordersData]);
        } catch (\Exception $e) {
            return $this->json(['success' => true, 'data' => [], 'db_error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/test/orders/{id}  — delete a single mobile order
     */
    #[Route('/orders/{id}', name: 'api_test_order_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteOrder(int $id, EntityManagerInterface $em): JsonResponse
    {
        try {
            $conn = $em->getConnection();
            $deleted = $conn->delete('mobile_orders', ['id' => $id]);
            if ($deleted === 0) {
                return $this->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }
            return $this->json(['success' => true, 'message' => "Order #$id deleted"]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * POST /api/test/order
     */
    #[Route('/order', name: 'api_test_order', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            if (empty($data['customer_email'])) {
                return $this->json(['error' => 'Missing customer_email'], Response::HTTP_BAD_REQUEST);
            }
            if (empty($data['items'])) {
                return $this->json(['error' => 'Missing items'], Response::HTTP_BAD_REQUEST);
            }

            // Build items list and calculate total
            $total = 0.0;
            $itemsForStorage = [];

            foreach ($data['items'] as $itemData) {
                $variantId = $itemData['variant_id'] ?? null;
                $quantity  = (int) ($itemData['quantity'] ?? 1);

                if (!$variantId) {
                    continue;
                }

                $variant = $variantRepository->find($variantId);
                if (!$variant) {
                    continue;
                }

                // Use the price the mobile cart sent (what the customer actually saw).
                // Only fall back to variant->getPrice() when not provided.
                $sentPrice = isset($itemData['unit_price']) && $itemData['unit_price'] !== null && (string)$itemData['unit_price'] !== ''
                    ? (float) $itemData['unit_price']
                    : null;
                $unitPrice = $sentPrice ?? (float) $variant->getPrice();
                $subtotal  = $unitPrice * $quantity;
                $total    += $subtotal;

                $itemsForStorage[] = [
                    'variant_id'   => $variant->getId(),
                    'product_name' => $variant->getProduct()->getName(),
                    'variant_name' => $variant->getDisplayName(),
                    'quantity'     => $quantity,
                    'unit_price'   => number_format($unitPrice, 2, '.', ''),
                    'subtotal'     => number_format($subtotal, 2, '.', ''),
                ];
            }

            $conn = $em->getConnection();

            // Ensure table exists
            $conn->executeStatement("
                CREATE TABLE IF NOT EXISTS mobile_orders (
                    id INT AUTO_INCREMENT NOT NULL,
                    customer_email VARCHAR(255) NOT NULL,
                    customer_name VARCHAR(255) DEFAULT NULL,
                    customer_phone VARCHAR(50) DEFAULT NULL,
                    items_json LONGTEXT NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    fulfillment_type VARCHAR(20) DEFAULT NULL,
                    payment_method VARCHAR(50) DEFAULT NULL,
                    delivery_address VARCHAR(255) DEFAULT NULL,
                    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            ");

            $conn->insert('mobile_orders', [
                'customer_email'   => $data['customer_email'],
                'customer_name'    => $data['customer_name'] ?? null,
                'customer_phone'   => $data['customer_phone'] ?? null,
                'items_json'       => json_encode($itemsForStorage),
                'status'           => 'pending',
                'fulfillment_type' => $data['fulfillment_type'] ?? 'pickup',
                'payment_method'   => $data['payment_method'] ?? 'cash',
                'delivery_address' => $data['delivery_address'] ?? null,
                'total'            => number_format($total, 2, '.', ''),
                'created_at'       => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $orderId = (int) $conn->lastInsertId();

            return $this->json([
                'message' => 'Order created successfully',
                'order'   => [
                    'id'           => $orderId,
                    'order_number' => 'MOB-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT),
                    'status'       => 'pending',
                    'total'        => number_format($total, 2, '.', ''),
                    'items_count'  => count($itemsForStorage),
                    'created_at'   => date('Y-m-d H:i:s'),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error'   => 'Order creation failed',
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()),
                'line'    => $e->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

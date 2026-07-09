<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Address;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public order endpoint for mobile app
 * Does not require session authentication, accepts Bearer token for user identification
 */
class PublicOrderController extends AbstractController
{
    /**
     * Create order with simple format (mobile app compatibility)
     * POST /orders
     */
    #[Route('/orders', name: 'public_orders_create', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $productRepository,
        ProductVariantRepository $variantRepository,
        UserRepository $userRepository,
        ActivityLogService $activityLogService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // For now, we'll create orders without user association
        // In production, implement proper JWT/API token validation
        $user = null;

        // Validate required fields
        $required = ['orderNumber', 'productId', 'customerName', 'quantity', 'totalAmount'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->json(
                    ['success' => false, 'error' => "Missing required field: {$field}"],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        try {
            // Find product
            $product = $productRepository->find((int)$data['productId']);
            if (!$product) {
                return $this->json(
                    ['success' => false, 'error' => 'Product not found'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Get first available variant
            $variant = $variantRepository->findOneBy(['product' => $product, 'isActive' => true]);
            if (!$variant) {
                return $this->json(
                    ['success' => false, 'error' => 'No active variant available for this product'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Get fulfillment type first
            $fulfillmentType = $data['fulfillmentType'] ?? 'pickup';

            // For delivery orders, create address. For pickup, create a dummy store address.
            $address = new Address();
            $address->setFullName($data['customerName']);
            
            if ($fulfillmentType === 'delivery' && isset($data['deliveryAddress']) && !empty($data['deliveryAddress'])) {
                $address->setStreetAddress($data['deliveryAddress']);
                $address->setCity('Manila'); // Parse from address if needed
                $address->setProvince('Metro Manila');
            } else {
                // Pickup - use store address
                $address->setStreetAddress('Store Pickup');
                $address->setCity('Store Location');
                $address->setProvince('Store Province');
            }
            
            $address->setPostalCode('1000');
            $address->setCountry('Philippines');
            $address->setPhone($data['customerPhone'] ?? '');
            
            if ($user) {
                $address->setUser($user);
            }
            
            $em->persist($address);

            // Create order
            $order = new Order();
            if ($user) {
                $order->setUser($user);
            }
            
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);
            
            // Set fulfillment status
            $order->setStatus($fulfillmentType === 'pickup' ? Order::STATUS_AWAITING_PICKUP : Order::STATUS_AWAITING_PAYMENT);

            // Create order item
            $orderItem = new OrderItem();
            $orderItem->setVariant($variant);
            $orderItem->setQuantity((int)$data['quantity']);
            $orderItem->setUnitPrice($variant->getPrice());
            
            $order->addItem($orderItem);
            $em->persist($orderItem);

            // Calculate totals
            $subtotal = bcmul($variant->getPrice(), (string)$data['quantity'], 2);
            $tax = bcmul($subtotal, '0.12', 2); // 12% VAT
            $shipping = $fulfillmentType === 'delivery' ? '50.00' : '0.00';

            $order->setSubtotal($subtotal);
            $order->setDiscount('0.00');
            $order->setTax($tax);
            $order->setShippingCost($shipping);
            $order->calculateTotal();
            
            // Set notes with payment method
            $paymentMethod = $data['paymentMethod'] ?? 'cash';
            $notes = "Payment: {$paymentMethod} | Fulfillment: {$fulfillmentType}";
            if (isset($data['notes'])) {
                $notes .= " | " . $data['notes'];
            }
            $order->setNotes($notes);

            $em->persist($order);
            $em->flush();

            // Log activity
            if ($user) {
                $activityLogService->logActivityFromRequest(
                    $user,
                    'ORDER_CREATED (Mobile - Legacy API)',
                    $request,
                    'public_orders_create',
                    [
                        'order_id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                        'product_id' => $product->getId(),
                        'product_name' => $product->getName(),
                        'quantity' => $data['quantity'],
                        'total_amount' => $data['totalAmount'],
                        'fulfillment_type' => $fulfillmentType,
                        'payment_method' => $paymentMethod,
                    ]
                );
            }

            return $this->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'total' => $order->getTotal(),
                    'created_at' => $order->getCreatedAt()?->format('c'),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to create order: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

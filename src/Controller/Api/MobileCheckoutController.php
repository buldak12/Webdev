<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/mobile')]
class MobileCheckoutController extends AbstractController
{
    /**
     * Create an order from mobile app (simplified, no address required for pickup)
     * POST /api/mobile/orders
     */
    #[Route('/orders', name: 'api_mobile_orders_create', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        ProductVariantRepository $variantRepository,
        UserRepository $userRepository,
        ActivityLogService $activityLogService
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validate required fields
            if (!isset($data['customer_email'], $data['customer_name'], $data['customer_phone'], $data['items'])) {
                return $this->json([
                    'error' => 'Missing required fields: customer_email, customer_name, customer_phone, items'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (empty($data['items'])) {
                return $this->json(['error' => 'Items cannot be empty'], Response::HTTP_BAD_REQUEST);
            }

            // Find or create user by email
            $user = $userRepository->findOneBy(['email' => $data['customer_email']]);
            if (!$user) {
                return $this->json([
                    'error' => 'User not found. Please register first.'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Create order
            $order = new Order();
            $order->setUser($user);
            $order->setStatus(Order::STATUS_AWAITING_PAYMENT);
            $order->setFulfillmentType($data['fulfillment_type'] ?? 'pickup');
            $order->setPaymentMethod($data['payment_method'] ?? 'cash');
            
            // Store customer contact info in notes
            $order->setNotes(sprintf(
                "Mobile Order\nName: %s\nPhone: %s\nEmail: %s",
                $data['customer_name'],
                $data['customer_phone'],
                $data['customer_email']
            ));

            $subtotal = 0.0;
            $itemCount = 0;

            // Add items
            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['variant_id'], $itemData['quantity'])) {
                    continue;
                }

                $variant = $variantRepository->find($itemData['variant_id']);
                if (!$variant) {
                    return $this->json([
                        'error' => sprintf('Product variant %d not found', $itemData['variant_id'])
                    ], Response::HTTP_BAD_REQUEST);
                }

                $quantity = (int) $itemData['quantity'];
                
                // Check stock
                if (!$variant->isInStock() || $variant->getAvailableStock() < $quantity) {
                    return $this->json([
                        'error' => sprintf('Product "%s" is out of stock or insufficient quantity', $variant->getProduct()->getName())
                    ], Response::HTTP_BAD_REQUEST);
                }

                // Create order item
                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setVariant($variant);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice($variant->getFinalPrice());
                $orderItem->setSubtotal($variant->getFinalPrice() * $quantity);

                $order->addItem($orderItem);
                $em->persist($orderItem);

                $subtotal += $variant->getFinalPrice() * $quantity;
                $itemCount++;

                // Reserve stock
                $variant->setReservedStock($variant->getReservedStock() + $quantity);
            }

            if ($itemCount === 0) {
                return $this->json([
                    'error' => 'No valid items in order'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Set order totals
            $order->setSubtotal($subtotal);
            $order->setShippingFee(0); // No shipping for pickup
            $order->setTotal($subtotal);

            $em->persist($order);
            $em->flush();

            // Log activity
            $activityLogService->log(
                'order_created',
                sprintf('Order #%d created via mobile app by %s', $order->getId(), $user->getEmail()),
                ['order_id' => $order->getId(), 'total' => $order->getTotal()],
                $user
            );

            return $this->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'subtotal' => $order->getSubtotal(),
                    'shipping_fee' => $order->getShippingFee(),
                    'total' => $order->getTotal(),
                    'items_count' => $itemCount,
                    'fulfillment_type' => $order->getFulfillmentType(),
                    'payment_method' => $order->getPaymentMethod(),
                    'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create order: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

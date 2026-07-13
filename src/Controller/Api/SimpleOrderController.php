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
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Log incoming data for debugging
            error_log('Order creation request: ' . json_encode($data));
            
            // Basic validation
            if (!isset($data['customer_email'])) {
                return $this->json(['error' => 'Missing customer_email'], Response::HTTP_BAD_REQUEST);
            }
            
            if (!isset($data['items']) || empty($data['items'])) {
                return $this->json(['error' => 'Missing items'], Response::HTTP_BAD_REQUEST);
            }

            // Find user
            $user = $userRepository->findOneBy(['email' => $data['customer_email']]);
            if (!$user) {
                return $this->json(['error' => 'User not found. Please register first.'], Response::HTTP_NOT_FOUND);
            }
            
            error_log('User found: ' . $user->getEmail());

            // Create or get address for the order (required by Order entity)
            $addresses = $user->getAddresses();
            if ($addresses->isEmpty()) {
                error_log('Creating new address for user');
                // Create a default address for pickup orders
                $address = new Address();
                $address->setUser($user);
                $address->setFullName($data['customer_name'] ?? ($user->getFirstName() . ' ' . $user->getLastName()));
                $address->setStreetAddress($data['delivery_address'] ?? 'Store Pickup');
                $address->setCity('Manila');
                $address->setProvince('Metro Manila');
                $address->setPostalCode('1000');
                $address->setCountry('Philippines');
                $address->setPhone($data['customer_phone'] ?? $user->getPhone() ?? '0000000000');
                $em->persist($address);
                $em->flush();
                error_log('Address created with ID: ' . $address->getId());
            } else {
                $address = $addresses->first();
                error_log('Using existing address ID: ' . $address->getId());
            }

            // Create order
            error_log('Creating order');
            $order = new Order();
            $order->setUser($user);
            $order->setStatus(Order::STATUS_AWAITING_PAYMENT);
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);
            
            // Add order notes
            $notes = sprintf(
                "Mobile App Order\nName: %s\nPhone: %s\nEmail: %s",
                $data['customer_name'] ?? 'N/A',
                $data['customer_phone'] ?? 'N/A',
                $data['customer_email']
            );
            $order->setNotes($notes);
            
            error_log('Order object created, adding items');

            $subtotal = 0.0;
            $itemCount = 0;

            // Add items to order
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
                
                error_log(sprintf('Adding item: variant_id=%d, quantity=%d', $itemData['variant_id'], $quantity));

                // Create order item
                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setVariant($variant);
                $orderItem->setQuantity($quantity);
                
                // Get the final price
                $unitPrice = (float) $variant->getFinalPrice();
                $itemSubtotal = $unitPrice * $quantity;
                
                $orderItem->setUnitPrice((string) number_format($unitPrice, 2, '.', ''));
                $orderItem->setSubtotal((string) number_format($itemSubtotal, 2, '.', ''));

                $order->addItem($orderItem);
                $em->persist($orderItem);

                $subtotal += $itemSubtotal;
                $itemCount++;
            }

            if ($itemCount === 0) {
                return $this->json(['error' => 'No valid items in order'], Response::HTTP_BAD_REQUEST);
            }
            
            error_log(sprintf('Added %d items, subtotal: %.2f', $itemCount, $subtotal));

            // Set order totals
            $order->setSubtotal((string) number_format($subtotal, 2, '.', ''));
            $order->setShippingCost('0.00');
            $order->setDiscount('0.00');
            $order->setTax('0.00');
            $order->setTotal((string) number_format($subtotal, 2, '.', ''));
            
            error_log('Order totals set, persisting to database');

            $em->persist($order);
            $em->flush();
            
            error_log('Order saved successfully with ID: ' . $order->getId());

            // Return order details
            return $this->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'subtotal' => $order->getSubtotal(),
                    'shipping_cost' => $order->getShippingCost(),
                    'discount' => $order->getDiscount(),
                    'tax' => $order->getTax(),
                    'total' => $order->getTotal(),
                    'items_count' => $itemCount,
                    'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            // Log the full error
            error_log('Order creation ERROR: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return $this->json([
                'error' => 'Order creation failed: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

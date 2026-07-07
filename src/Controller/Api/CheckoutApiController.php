<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductVariantRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class CheckoutApiController extends AbstractController
{
    /**
     * Get user's addresses
     * GET /api/addresses
     */
    #[Route('/addresses', name: 'api_addresses', methods: ['GET'])]
    public function getAddresses(AddressRepository $addressRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $addresses = $user->getAddresses();

        $data = array_map(fn($addr) => [
            'id' => $addr->getId(),
            'full_name' => $addr->getFullName(),
            'street_address' => $addr->getStreetAddress(),
            'barangay' => $addr->getBarangay(),
            'city' => $addr->getCity(),
            'province' => $addr->getProvince(),
            'postal_code' => $addr->getPostalCode(),
            'country' => $addr->getCountry(),
            'phone' => $addr->getPhone(),
            'region' => $addr->getRegion(),
        ], $addresses->toArray());

        return $this->json(['addresses' => $data]);
    }

    /**
     * Add a new address
     * POST /api/addresses
     */
    #[Route('/addresses', name: 'api_addresses_create', methods: ['POST'])]
    public function createAddress(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $data = json_decode($request->getContent(), true);

        $required = ['full_name', 'street_address', 'city', 'province', 'postal_code', 'country', 'phone'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->json(
                    ['error' => "Missing required field: {$field}"],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $address = new Address();
        $address->setUser($user);
        $address->setFullName($data['full_name']);
        $address->setStreetAddress($data['street_address']);
        $address->setBarangay($data['barangay'] ?? null);
        $address->setCity($data['city']);
        $address->setProvince($data['province']);
        $address->setPostalCode($data['postal_code']);
        $address->setCountry($data['country']);
        $address->setPhone($data['phone']);

        $em->persist($address);
        $em->flush();

        return $this->json([
            'message' => 'Address added',
            'address' => [
                'id' => $address->getId(),
                'full_name' => $address->getFullName(),
                'street_address' => $address->getStreetAddress(),
                'city' => $address->getCity(),
                'province' => $address->getProvince(),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Create an order (checkout)
     * POST /api/orders
     */
    #[Route('/orders', name: 'api_orders_create', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        AddressRepository $addressRepository,
        ProductVariantRepository $variantRepository,
        ActivityLogService $activityLogService
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $data = json_decode($request->getContent(), true);

        // Validate
        if (!isset($data['shipping_address_id'], $data['items']) || empty($data['items'])) {
            return $this->json(
                ['error' => 'Missing shipping_address_id or items'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $shippingAddress = $addressRepository->find($data['shipping_address_id']);
        if (!$shippingAddress || $shippingAddress->getUser() !== $user) {
            return $this->json(
                ['error' => 'Invalid shipping address'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $billingAddress = isset($data['billing_address_id']) && $data['billing_address_id']
            ? $addressRepository->find($data['billing_address_id'])
            : $shippingAddress;

        // Create order
        $order = new Order();
        $order->setUser($user);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setStatus(Order::STATUS_AWAITING_PAYMENT);

        $subtotal = '0.00';
        $itemSummary = [];

        // Add items
        foreach ($data['items'] as $itemData) {
            if (!isset($itemData['variant_id'], $itemData['quantity'])) {
                continue;
            }

            $variant = $variantRepository->find($itemData['variant_id']);
            if (!$variant) {
                return $this->json(
                    ['error' => sprintf('Variant %d not found', $itemData['variant_id'])],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $quantity = (int) $itemData['quantity'];
            if (!$variant->isInStock() || $variant->getAvailableStock() < $quantity) {
                return $this->json(
                    ['error' => sprintf('%s is out of stock', $variant->getDisplayName())],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $item = new OrderItem();
            $item->setVariant($variant);
            $item->setQuantity($quantity);
            $item->setUnitPrice($variant->getPrice());

            $order->addItem($item);
            $em->persist($item);

            $itemTotal = bcmul($variant->getPrice(), (string) $quantity, 2);
            $subtotal = bcadd($subtotal, $itemTotal, 2);

            // Track items for activity log
            $itemSummary[] = [
                'product' => $variant->getProduct()?->getName(),
                'variant' => $variant->getDisplayName(),
                'quantity' => $quantity,
                'price' => $variant->getPrice(),
            ];
        }

        // Set pricing
        $discount = isset($data['discount']) ? (string) $data['discount'] : '0.00';
        $tax = bcmul(bcsub($subtotal, $discount, 2), '0.12', 2); // 12% VAT
        $shipping = isset($data['shipping_cost']) ? (string) $data['shipping_cost'] : '0.00';

        $order->setSubtotal($subtotal);
        $order->setDiscount($discount);
        $order->setTax($tax);
        $order->setShippingCost($shipping);
        $order->calculateTotal();
        $order->setNotes($data['notes'] ?? null);

        $em->persist($order);
        $em->flush();

        // Log order creation to activity log
        $activityLogService->logActivityFromRequest(
            $user,
            'ORDER_CREATED (Mobile App)',
            $request,
            'api_orders_create',
            [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'total_amount' => $order->getTotal(),
                'items_count' => count($itemSummary),
                'items' => $itemSummary,
                'status' => $order->getStatus(),
            ]
        );

        return $this->json([
            'message' => 'Order created',
            'order' => [
                'id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'subtotal' => $order->getSubtotal(),
                'discount' => $order->getDiscount(),
                'tax' => $order->getTax(),
                'shipping' => $order->getShippingCost(),
                'total' => $order->getTotal(),
                'created_at' => $order->getCreatedAt()?->format('c'),
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Get user's orders
     * GET /api/orders
     */
    #[Route('/orders', name: 'api_orders_list', methods: ['GET'])]
    public function getOrders(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $limit = $request->query->getInt('limit', 10);
        $offset = $request->query->getInt('offset', 0);

        $orders = $orderRepository->findByUser($user, $limit + $offset);

        $data = array_map(fn($order) => [
            'id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'status_label' => Order::STATUSES[$order->getStatus()] ?? $order->getStatus(),
            'total' => $order->getTotal(),
            'items_count' => $order->getItemCount(),
            'created_at' => $order->getCreatedAt()?->format('c'),
            'paid_at' => $order->getPaidAt()?->format('c'),
            'shipped_at' => $order->getShippedAt()?->format('c'),
        ], array_slice($orders, $offset, $limit));

        return $this->json([
            'total' => count($orders),
            'limit' => $limit,
            'offset' => $offset,
            'orders' => $data,
        ]);
    }

    /**
     * Get order details
     * GET /api/orders/{id}
     */
    #[Route('/orders/{id}', name: 'api_order_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getOrder(int $id, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $order = $orderRepository->find($id);

        if (!$order || $order->getUser() !== $user) {
            return $this->json(
                ['error' => 'Order not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $items = array_map(fn($item) => [
            'id' => $item->getId(),
            'product_name' => $item->getProductName(),
            'variant_attributes' => $item->getVariantAttributes(),
            'quantity' => $item->getQuantity(),
            'unit_price' => $item->getUnitPrice(),
            'total' => $item->getTotal(),
        ], $order->getItems()->toArray());

        return $this->json([
            'id' => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'status' => $order->getStatus(),
            'status_label' => Order::STATUSES[$order->getStatus()] ?? $order->getStatus(),
            'subtotal' => $order->getSubtotal(),
            'discount' => $order->getDiscount(),
            'tax' => $order->getTax(),
            'shipping' => $order->getShippingCost(),
            'total' => $order->getTotal(),
            'items' => $items,
            'shipping_address' => [
                'full_name' => $order->getShippingAddress()?->getFullName(),
                'street' => $order->getShippingAddress()?->getStreetAddress(),
                'city' => $order->getShippingAddress()?->getCity(),
                'province' => $order->getShippingAddress()?->getProvince(),
                'postal_code' => $order->getShippingAddress()?->getPostalCode(),
                'phone' => $order->getShippingAddress()?->getPhone(),
            ],
            'created_at' => $order->getCreatedAt()?->format('c'),
            'paid_at' => $order->getPaidAt()?->format('c'),
            'shipped_at' => $order->getShippedAt()?->format('c'),
        ]);
    }
}

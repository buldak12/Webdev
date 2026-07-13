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

    #[Route('/order', name: 'api_test_order', methods: ['POST'])]
    public function createSimpleOrder(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Basic validation
            if (!isset($data['customer_email'], $data['items']) || empty($data['items'])) {
                return $this->json([
                    'error' => 'Missing customer_email or items'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find user
            $user = $userRepository->findOneBy(['email' => $data['customer_email']]);
            if (!$user) {
                return $this->json([
                    'error' => 'User not found. Please register first.'
                ], Response::HTTP_NOT_FOUND);
            }

            // Create or get dummy address for mobile pickup orders
            $addresses = $user->getAddresses();
            if ($addresses->isEmpty()) {
                $address = new Address();
                $address->setUser($user);
                $address->setFullName($data['customer_name'] ?? 'Mobile Customer');
                $address->setStreetAddress('Store Pickup');
                $address->setCity('Manila');
                $address->setProvince('Metro Manila');
                $address->setPostalCode('1000');
                $address->setCountry('Philippines');
                $address->setPhone($data['customer_phone'] ?? '0000000000');
                $em->persist($address);
                $em->flush();
            } else {
                $address = $addresses->first();
            }

            // Create order
            $order = new Order();
            $order->setUser($user);
            $order->setStatus(Order::STATUS_AWAITING_PAYMENT);
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);
            
            // Add notes
            $notes = sprintf(
                "Mobile App Order\nName: %s\nPhone: %s\nEmail: %s",
                $data['customer_name'] ?? 'N/A',
                $data['customer_phone'] ?? 'N/A',
                $data['customer_email']
            );
            $order->setNotes($notes);

            $subtotal = 0.0;

            // Add items
            foreach ($data['items'] as $itemData) {
                if (!isset($itemData['variant_id'], $itemData['quantity'])) {
                    continue;
                }

                $variant = $variantRepository->find($itemData['variant_id']);
                if (!$variant) {
                    return $this->json([
                        'error' => "Variant {$itemData['variant_id']} not found"
                    ], Response::HTTP_BAD_REQUEST);
                }

                $quantity = (int) $itemData['quantity'];

                // Create order item
                $orderItem = new OrderItem();
                $orderItem->setOrder($order);
                $orderItem->setVariant($variant);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice((string)$variant->getFinalPrice());
                $orderItem->setSubtotal((string)($variant->getFinalPrice() * $quantity));

                $order->addItem($orderItem);
                $em->persist($orderItem);

                $subtotal += $variant->getFinalPrice() * $quantity;
            }

            // Set totals
            $order->setSubtotal((string)$subtotal);
            $order->setShippingCost('0');
            $order->setTotal((string)$subtotal);

            $em->persist($order);
            $em->flush();

            return $this->json([
                'message' => 'Order created successfully',
                'order' => [
                    'id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'status' => $order->getStatus(),
                    'subtotal' => $order->getSubtotal(),
                    'total' => $order->getTotal(),
                    'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Order creation failed: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

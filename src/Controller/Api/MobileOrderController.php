<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simplified mobile order endpoint
 */
class MobileOrderController extends AbstractController
{
    #[Route('/api/mobile/orders', name: 'api_mobile_orders', methods: ['POST'])]
    public function createOrder(
        Request $request,
        EntityManagerInterface $em,
        ProductVariantRepository $variantRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            // Find or create user
            $email = $data['customer_email'] ?? 'guest_' . uniqid() . '@mobile.app';
            $user = $userRepository->findOneBy(['email' => $email]);
            
            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($data['customer_name'] ?? 'Guest');
                $user->setLastName('User');
                $user->setRoles(['ROLE_CUSTOMER']);
                $user->setPassword($passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));
                $user->setIsEmailVerified(false);
                $em->persist($user);
                $em->flush();
            }

            // Create address
            $address = new Address();
            $address->setUser($user);
            $address->setFullName($data['customer_name'] ?? 'Guest');
            $address->setStreetAddress($data['delivery_address'] ?? 'Store Pickup');
            $address->setCity('Manila');
            $address->setProvince('Metro Manila');
            $address->setPostalCode('1000');
            $address->setCountry('Philippines');
            $address->setPhone($data['customer_phone'] ?? '');
            $em->persist($address);
            $em->flush();

            // Create order
            $order = new Order();
            $order->setUser($user);
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);
            $order->setStatus(Order::STATUS_PENDING);

            $subtotal = '0.00';

            // Add items
            foreach ($data['items'] ?? [] as $itemData) {
                $variant = $variantRepository->find($itemData['variant_id'] ?? 0);
                if (!$variant) continue;

                $item = new OrderItem();
                $item->setVariant($variant);
                $item->setQuantity((int)($itemData['quantity'] ?? 1));
                $item->setUnitPrice($variant->getPrice());
                $order->addItem($item);
                $em->persist($item);

                $subtotal = bcadd($subtotal, bcmul($variant->getPrice(), (string)$item->getQuantity(), 2), 2);
            }

            // Calculate totals
            $tax = bcmul($subtotal, '0.12', 2);
            $order->setSubtotal($subtotal);
            $order->setDiscount('0.00');
            $order->setTax($tax);
            $order->setShippingCost('0.00');
            $order->calculateTotal();
            $order->setNotes(($data['payment_method'] ?? 'cash') . ' | ' . ($data['fulfillment_type'] ?? 'pickup'));

            $em->persist($order);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Order created',
                'order' => [
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
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

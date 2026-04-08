<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\User;
use App\Form\AddressType;
use App\Repository\AddressRepository;
use App\Repository\CategoryRepository;
use App\Service\CartService;
use App\Service\OrderService;
use App\Service\PaymentService;
use App\Service\ShippingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/checkout')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private OrderService $orderService,
        private PaymentService $paymentService,
        private ShippingService $shippingService,
        private AddressRepository $addressRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'checkout_index')]
    public function index(Request $request): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);
        
        if ($cart->isEmpty()) {
            $this->addFlash('warning', 'Your cart is empty');
            return $this->redirectToRoute('cart_index');
        }
        
        // Validate cart items
        $errors = $this->cartService->validateCartItems($cart);
        if (!empty($errors)) {
            $this->addFlash('error', 'Some items in your cart are unavailable. Please review your cart.');
            return $this->redirectToRoute('cart_index');
        }
        
        // Check age verification if needed
        if ($cart->requiresAgeVerification() && !$user->isAgeVerified()) {
            $this->addFlash('warning', 'Age verification is required for some items in your cart. Please verify your age first.');
            return $this->redirectToRoute('account_verify_age');
        }
        
        // Get user addresses
        $addresses = $this->addressRepository->findBy(['user' => $user], ['isDefaultShipping' => 'DESC', 'createdAt' => 'DESC']);
        $defaultAddress = null;
        foreach ($addresses as $addr) {
            if ($addr->isDefaultShipping()) {
                $defaultAddress = $addr;
                break;
            }
        }
        
        // Create new address form
        $newAddress = new Address();
        $newAddress->setUser($user);
        $newAddress->setFullName($user->getFullName());
        $addressForm = $this->createForm(AddressType::class, $newAddress);
        $addressForm->handleRequest($request);
        
        if ($addressForm->isSubmitted() && $addressForm->isValid()) {
            $this->entityManager->persist($newAddress);
            $this->entityManager->flush();
            
            return $this->redirectToRoute('checkout_index', ['address' => $newAddress->getId()]);
        }
        
        // Get selected or default address
        $selectedAddressId = $request->query->getInt('address') ?: ($defaultAddress?->getId());
        $selectedAddress = $selectedAddressId ? $this->addressRepository->find($selectedAddressId) : null;
        
        // Calculate shipping if address selected
        $shippingCost = '0.00';
        $deliveryEstimate = null;
        if ($selectedAddress) {
            $summary = $this->cartService->getCartSummary($cart);
            $shippingCost = $this->shippingService->calculateShippingCost(
                $selectedAddress,
                $summary['subtotal'],
                $cart->getPromoCode()?->isFreeShipping() ?? false
            );
            $deliveryEstimate = $this->shippingService->getEstimatedDeliveryDate($selectedAddress);
        }
        
        $summary = $this->cartService->getCartSummary($cart);
        $paymentGateways = $this->paymentService->getAvailableGateways();

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'summary' => $summary,
            'addresses' => $addresses,
            'selected_address' => $selectedAddress,
            'address_form' => $addressForm->createView(),
            'shipping_cost' => $shippingCost,
            'delivery_estimate' => $deliveryEstimate,
            'payment_gateways' => $paymentGateways,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/place-order', name: 'checkout_place_order', methods: ['POST'])]
    public function placeOrder(Request $request): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->cartService->getCart($user);
        
        if ($cart->isEmpty()) {
            return $this->redirectToRoute('cart_index');
        }
        
        $addressId = $request->request->getInt('address_id');
        $paymentGateway = $request->request->get('payment_gateway');
        
        $address = $this->addressRepository->find($addressId);
        if (!$address || $address->getUser() !== $user) {
            $this->addFlash('error', 'Please select a valid shipping address');
            return $this->redirectToRoute('checkout_index');
        }
        
        if (empty($paymentGateway)) {
            $this->addFlash('error', 'Please select a payment method');
            return $this->redirectToRoute('checkout_index', ['address' => $addressId]);
        }
        
        try {
            // Create the order
            $order = $this->orderService->createOrderFromCart($cart, $user, $address);
            
            // Clear the cart
            $this->cartService->clearCart($cart);
            
            // Process payment
            $paymentResult = $this->orderService->processPayment($order, $paymentGateway);

            if (($paymentResult['simulated'] ?? false) === true) {
                $this->addFlash('success', $paymentResult['message'] ?? 'Test mode: payment auto-confirmed.');
                return $this->redirectToRoute('checkout_success', ['order' => $order->getId()]);
            }
            
            // Handle payment based on method
            if ($paymentResult['payment_method'] === 'cod') {
                // COD is automatically confirmed
                $this->addFlash('success', 'Order placed successfully! Pay ₱' . number_format($order->getTotal(), 2) . ' when your order arrives.');
                return $this->redirectToRoute('checkout_success', ['order' => $order->getId()]);
            }
            
            if ($paymentResult['payment_method'] === 'redirect' && isset($paymentResult['checkout_url'])) {
                // For production, redirect to payment gateway
                // For now, simulate success
                $this->addFlash('success', 'Order placed! Redirecting to payment...');
                return $this->redirectToRoute('checkout_success', ['order' => $order->getId()]);
            }
            
            if ($paymentResult['payment_method'] === 'bank_transfer') {
                $this->addFlash('info', 'Order placed! Please complete the bank transfer using the details below.');
                return $this->redirectToRoute('checkout_success', ['order' => $order->getId()]);
            }
            
            return $this->redirectToRoute('checkout_success', ['order' => $order->getId()]);
            
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('checkout_index', ['address' => $addressId]);
        }
    }

    #[Route('/success/{order}', name: 'checkout_success')]
    public function success(int $order): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        /** @var User $user */
        $user = $this->getUser();
        
        $orderEntity = $this->entityManager->getRepository(\App\Entity\Order::class)->find($order);
        
        if (!$orderEntity || $orderEntity->getUser() !== $user) {
            throw $this->createNotFoundException('Order not found');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $orderEntity,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => 0,
        ]);
    }

    private function denyAdminShoppingAccess(): ?Response
    {
        if (!$this->isGranted(User::ROLE_ADMIN)) {
            return null;
        }

        $this->addFlash('warning', 'Admin account cannot shop. Please use a customer account for purchases.');
        return $this->redirectToRoute('admin_dashboard');
    }
}

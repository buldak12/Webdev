<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Cart;
use App\Entity\LoyaltyTransaction;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\Shipment;
use App\Entity\User;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OrderService
{
    private const TAX_RATE = '0.12';
    private const LOYALTY_POINTS_PER_100 = 1; // 1 point per 100 PHP spent

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private InventoryService $inventoryService,
        private ShippingService $shippingService,
        private PaymentService $paymentService,
        private ?LoggerInterface $logger = null
    ) {}

    public function createOrderFromCart(Cart $cart, User $user, Address $shippingAddress, ?Address $billingAddress = null): Order
    {
        if ($cart->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        // Validate age verification for restricted products
        if ($cart->requiresAgeVerification() && !$user->isAgeVerified()) {
            throw new \InvalidArgumentException('Age verification required for this order');
        }

        $order = new Order();
        $order->setUser($user);
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress ?? $shippingAddress);

        // Calculate totals
        $subtotal = $cart->getSubtotal();
        $discount = '0.00';

        // Apply promo code if present
        $promoCode = $cart->getPromoCode();
        if ($promoCode && $promoCode->isValid()) {
            $discount = $promoCode->calculateDiscount($subtotal);
            $order->setPromoCode($promoCode);
            $promoCode->incrementUsageCount();
        }

        // Calculate shipping
        $taxableAmount = bcsub($subtotal, $discount, 2);
        $shippingCost = $this->shippingService->calculateShippingCost(
            $shippingAddress,
            $taxableAmount,
            $promoCode?->isFreeShipping() ?? false
        );

        // Calculate tax (12% VAT)
        $tax = bcmul($taxableAmount, self::TAX_RATE, 2);

        // Set order totals
        $order->setSubtotal($subtotal);
        $order->setDiscount($discount);
        $order->setTax($tax);
        $order->setShippingCost($shippingCost);
        $order->calculateTotal();

        // Calculate loyalty points
        $pointsEarned = (int) bcdiv($order->getTotal(), '100', 0) * self::LOYALTY_POINTS_PER_100;
        $order->setLoyaltyPointsEarned($pointsEarned);

        // Set initial status
        if ($cart->requiresAgeVerification() && !$user->isAgeVerified()) {
            $order->setStatus(Order::STATUS_AWAITING_VERIFICATION);
        } else {
            $order->setStatus(Order::STATUS_AWAITING_PAYMENT);
        }

        $this->entityManager->persist($order);

        // Create order items from cart items
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = OrderItem::createFromCartItem($cartItem);
            $order->addItem($orderItem);
            $this->entityManager->persist($orderItem);
        }

        // Create shipment
        $shipment = new Shipment();
        $shipment->setOrder($order);
        $shipment->setShippingCost($shippingCost);

        // Set estimated delivery
        $deliveryEstimate = $this->shippingService->getEstimatedDeliveryDate($shippingAddress);
        $shipment->setEstimatedDelivery($deliveryEstimate['max_date']);

        $this->entityManager->persist($shipment);

        $this->entityManager->flush();

        $this->logger?->info('Order created', [
            'order_number' => $order->getOrderNumber(),
            'user_id' => $user->getId(),
            'total' => $order->getTotal(),
        ]);

        return $order;
    }

    public function processPayment(Order $order, string $gateway): array
    {
        if ($order->isPaid()) {
            return ['success' => false, 'error' => 'Order is already paid'];
        }

        $payment = $this->paymentService->createPayment($order, $gateway);
        return $this->paymentService->processPayment($payment);
    }

    public function confirmPayment(Order $order, Payment $payment): void
    {
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $order->setStatus(Order::STATUS_PAID);
        $order->setPaidAt(new \DateTime());

        // Confirm stock deduction
        foreach ($order->getItems() as $item) {
            if ($item->getVariant()) {
                $this->inventoryService->confirmStockDeduction($item->getVariant(), $item->getQuantity());
            }
        }

        // Award loyalty points
        $user = $order->getUser();
        if ($order->getLoyaltyPointsEarned() > 0) {
            $user->addLoyaltyPoints($order->getLoyaltyPointsEarned());
            
            $transaction = LoyaltyTransaction::createEarned(
                $user,
                $order->getLoyaltyPointsEarned(),
                sprintf('Order %s completed', $order->getOrderNumber()),
                'order',
                $order->getId()
            );
            $this->entityManager->persist($transaction);
        }

        $this->entityManager->flush();

        $this->logger?->info('Payment confirmed', [
            'order_number' => $order->getOrderNumber(),
            'payment_id' => $payment->getTransactionId(),
        ]);
    }

    public function cancelOrder(Order $order, ?string $reason = null): void
    {
        if (!$order->isCancellable()) {
            throw new \InvalidArgumentException('Order cannot be cancelled');
        }

        // Release reserved stock
        foreach ($order->getItems() as $item) {
            if ($item->getVariant()) {
                $this->inventoryService->releaseReservedStock($item->getVariant(), $item->getQuantity());
            }
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $order->setInternalNotes($reason ? "Cancellation reason: $reason" : null);

        $this->entityManager->flush();

        $this->logger?->info('Order cancelled', [
            'order_number' => $order->getOrderNumber(),
            'reason' => $reason,
        ]);
    }

    public function refundOrder(Order $order, ?string $reason = null): array
    {
        if (!$order->isRefundable()) {
            return ['success' => false, 'error' => 'Order is not refundable'];
        }

        $payment = $order->getSuccessfulPayment();
        if (!$payment) {
            return ['success' => false, 'error' => 'No successful payment found'];
        }

        // Process refund
        $refundResult = $this->paymentService->refundPayment($payment, $reason);
        if (!$refundResult['success']) {
            return $refundResult;
        }

        $order->setStatus(Order::STATUS_REFUNDED);
        
        // Deduct loyalty points if awarded
        $user = $order->getUser();
        if ($order->getLoyaltyPointsEarned() > 0) {
            $user->deductLoyaltyPoints($order->getLoyaltyPointsEarned());
            
            $transaction = LoyaltyTransaction::createRedeemed(
                $user,
                $order->getLoyaltyPointsEarned(),
                sprintf('Order %s refunded', $order->getOrderNumber()),
                'order',
                $order->getId()
            );
            $this->entityManager->persist($transaction);
        }

        $this->entityManager->flush();

        return ['success' => true, 'message' => 'Order refunded successfully'];
    }

    public function markAsProcessing(Order $order): void
    {
        if ($order->getStatus() !== Order::STATUS_PAID) {
            throw new \InvalidArgumentException('Only paid orders can be marked as processing');
        }

        $order->setStatus(Order::STATUS_PROCESSING);
        $order->getShipment()?->setStatus(Shipment::STATUS_PREPARING);

        $this->entityManager->flush();
    }

    public function markAsReadyToShip(Order $order, ?User $packedBy = null): void
    {
        if ($order->getStatus() !== Order::STATUS_PROCESSING) {
            throw new \InvalidArgumentException('Only processing orders can be marked as ready to ship');
        }

        $order->setStatus(Order::STATUS_READY_TO_SHIP);
        
        $shipment = $order->getShipment();
        if ($shipment && $packedBy) {
            $shipment->markAsPacked($packedBy);
        }

        $this->entityManager->flush();
    }

    public function markAsShipped(Order $order, string $courier, string $trackingNumber): void
    {
        $order->setStatus(Order::STATUS_SHIPPED);
        $order->setShippedAt(new \DateTime());

        $shipment = $order->getShipment();
        if ($shipment) {
            $shipment->markAsShipped($courier, $trackingNumber);
        }

        $this->entityManager->flush();

        $this->logger?->info('Order shipped', [
            'order_number' => $order->getOrderNumber(),
            'courier' => $courier,
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function markAsDelivered(Order $order): void
    {
        $order->setStatus(Order::STATUS_DELIVERED);
        $order->setDeliveredAt(new \DateTime());

        $shipment = $order->getShipment();
        if ($shipment) {
            $shipment->markAsDelivered();
        }

        $this->entityManager->flush();

        $this->logger?->info('Order delivered', [
            'order_number' => $order->getOrderNumber(),
        ]);
    }

    public function getOrderSummary(): array
    {
        return [
            'pending' => $this->orderRepository->countByStatus(Order::STATUS_PENDING),
            'awaiting_payment' => $this->orderRepository->countByStatus(Order::STATUS_AWAITING_PAYMENT),
            'paid' => $this->orderRepository->countByStatus(Order::STATUS_PAID),
            'processing' => $this->orderRepository->countByStatus(Order::STATUS_PROCESSING),
            'ready_to_ship' => $this->orderRepository->countByStatus(Order::STATUS_READY_TO_SHIP),
            'shipped' => $this->orderRepository->countByStatus(Order::STATUS_SHIPPED),
        ];
    }

    public function getPendingFulfillmentOrders(): array
    {
        return $this->orderRepository->findPendingFulfillment();
    }

    public function getReadyToShipOrders(): array
    {
        return $this->orderRepository->findReadyToShip();
    }
}

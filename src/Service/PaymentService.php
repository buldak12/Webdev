<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private ?LoggerInterface $logger = null,
        private bool $simulatePayments = true
    ) {}

    public function createPayment(Order $order, string $gateway): Payment
    {
        $payment = new Payment();
        $payment->setOrder($order);
        $payment->setGateway($gateway);
        $payment->setAmount($order->getTotal());
        $payment->setStatus(Payment::STATUS_PENDING);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function processPayment(Payment $payment): array
    {
        $payment->setStatus(Payment::STATUS_PROCESSING);
        $this->entityManager->flush();

        return match ($payment->getGateway()) {
            Payment::GATEWAY_GCASH => $this->processGCash($payment),
            Payment::GATEWAY_MAYA => $this->processMaya($payment),
            Payment::GATEWAY_CREDIT_CARD => $this->processCreditCard($payment),
            Payment::GATEWAY_COD => $this->processCOD($payment),
            Payment::GATEWAY_BANK_TRANSFER => $this->processBankTransfer($payment),
            default => ['success' => false, 'error' => 'Unsupported payment gateway'],
        };
    }

    private function processGCash(Payment $payment): array
    {
        if ($this->simulatePayments) {
            return $this->simulateSuccessfulPayment($payment, 'GCash');
        }

        // In production, this would integrate with GCash API
        // For now, return a simulated checkout URL
        $checkoutUrl = sprintf(
            'https://gcash.com/checkout?txn=%s&amount=%s',
            $payment->getTransactionId(),
            $payment->getAmount()
        );

        $this->logger?->info('GCash payment initiated', [
            'transaction_id' => $payment->getTransactionId(),
            'amount' => $payment->getAmount(),
        ]);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'payment_method' => 'redirect',
            'message' => 'Redirecting to GCash...',
        ];
    }

    private function processMaya(Payment $payment): array
    {
        if ($this->simulatePayments) {
            return $this->simulateSuccessfulPayment($payment, 'Maya');
        }

        // In production, this would integrate with Maya (PayMaya) API
        $checkoutUrl = sprintf(
            'https://maya.ph/checkout?txn=%s&amount=%s',
            $payment->getTransactionId(),
            $payment->getAmount()
        );

        $this->logger?->info('Maya payment initiated', [
            'transaction_id' => $payment->getTransactionId(),
            'amount' => $payment->getAmount(),
        ]);

        return [
            'success' => true,
            'checkout_url' => $checkoutUrl,
            'payment_method' => 'redirect',
            'message' => 'Redirecting to Maya...',
        ];
    }

    private function processCreditCard(Payment $payment): array
    {
        if ($this->simulatePayments) {
            return $this->simulateSuccessfulPayment($payment, 'Credit Card');
        }

        // In production, integrate with payment processor like Stripe or PayMongo
        return [
            'success' => true,
            'payment_method' => 'card_form',
            'client_key' => 'pk_test_xxxx', // Would be actual publishable key
            'message' => 'Enter card details',
        ];
    }

    private function processCOD(Payment $payment): array
    {
        // COD is automatically approved but payment is collected on delivery
        $payment->markAsCompleted('COD-' . date('YmdHis'), [
            'mode' => $this->simulatePayments ? 'test' : 'live',
            'simulated' => $this->simulatePayments,
        ]);
        $this->updateOrderStatus($payment);
        $this->entityManager->flush();

        $this->logger?->info('COD payment confirmed', [
            'transaction_id' => $payment->getTransactionId(),
            'amount' => $payment->getAmount(),
        ]);

        return [
            'success' => true,
            'payment_method' => 'cod',
            'simulated' => $this->simulatePayments,
            'message' => $this->simulatePayments
                ? 'Test mode: COD order auto-confirmed for fulfillment.'
                : 'Cash on Delivery confirmed. Please prepare exact amount.',
        ];
    }

    private function processBankTransfer(Payment $payment): array
    {
        if ($this->simulatePayments) {
            return $this->simulateSuccessfulPayment($payment, 'Bank Transfer');
        }

        return [
            'success' => true,
            'payment_method' => 'bank_transfer',
            'bank_details' => [
                'bank_name' => 'BDO Unibank',
                'account_name' => 'VapeShop Philippines',
                'account_number' => '1234-5678-9012',
            ],
            'reference' => $payment->getTransactionId(),
            'message' => 'Please transfer the exact amount and use the reference number.',
        ];
    }

    public function handleWebhook(string $gateway, array $payload): array
    {
        $this->logger?->info('Payment webhook received', [
            'gateway' => $gateway,
            'payload' => $payload,
        ]);

        return match ($gateway) {
            'gcash' => $this->handleGCashWebhook($payload),
            'maya' => $this->handleMayaWebhook($payload),
            'credit_card' => $this->handleCardWebhook($payload),
            default => ['success' => false, 'error' => 'Unknown gateway'],
        };
    }

    private function handleGCashWebhook(array $payload): array
    {
        $transactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$transactionId) {
            return ['success' => false, 'error' => 'Missing transaction ID'];
        }

        $payment = $this->paymentRepository->findByTransactionId($transactionId);
        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found'];
        }

        if ($status === 'SUCCESS') {
            $payment->markAsCompleted(
                $payload['gcash_reference'] ?? null,
                $payload
            );
            $this->updateOrderStatus($payment);
        } else {
            $payment->markAsFailed(
                $payload['error_message'] ?? 'Payment failed',
                $payload
            );
        }

        $this->entityManager->flush();

        return ['success' => true, 'payment_status' => $payment->getStatus()];
    }

    private function handleMayaWebhook(array $payload): array
    {
        // Similar implementation for Maya
        return $this->handleGCashWebhook($payload);
    }

    private function handleCardWebhook(array $payload): array
    {
        // Similar implementation for card payments
        return $this->handleGCashWebhook($payload);
    }

    public function confirmPayment(Payment $payment, string $externalReference = null, array $metadata = null): void
    {
        $payment->markAsCompleted($externalReference, $metadata);
        $this->updateOrderStatus($payment);
        $this->entityManager->flush();
    }

    public function failPayment(Payment $payment, string $reason, array $metadata = null): void
    {
        $payment->markAsFailed($reason, $metadata);
        $this->entityManager->flush();
    }

    private function updateOrderStatus(Payment $payment): void
    {
        $order = $payment->getOrder();
        if ($payment->isSuccessful() && !$order->isPaid()) {
            $order->setStatus(Order::STATUS_PAID);
        }
    }

    private function simulateSuccessfulPayment(Payment $payment, string $gatewayLabel): array
    {
        $reference = sprintf(
            'SIM-%s-%s',
            strtoupper(substr($payment->getGateway() ?? 'pay', 0, 4)),
            date('YmdHis')
        );

        $payment->markAsCompleted($reference, [
            'mode' => 'test',
            'simulated' => true,
            'gateway_label' => $gatewayLabel,
        ]);
        $this->updateOrderStatus($payment);
        $this->entityManager->flush();

        $this->logger?->info('Simulated payment confirmed', [
            'gateway' => $payment->getGateway(),
            'transaction_id' => $payment->getTransactionId(),
            'amount' => $payment->getAmount(),
        ]);

        return [
            'success' => true,
            'payment_method' => 'simulated',
            'simulated' => true,
            'message' => sprintf('Test mode: %s payment auto-confirmed.', $gatewayLabel),
        ];
    }

    public function refundPayment(Payment $payment, string $reason = null): array
    {
        if (!$payment->isSuccessful()) {
            return ['success' => false, 'error' => 'Cannot refund unsuccessful payment'];
        }

        // In production, call gateway refund API
        $payment->setStatus(Payment::STATUS_REFUNDED);
        $this->entityManager->flush();

        $this->logger?->info('Payment refunded', [
            'transaction_id' => $payment->getTransactionId(),
            'reason' => $reason,
        ]);

        return ['success' => true, 'message' => 'Refund processed successfully'];
    }

    public function getAvailableGateways(): array
    {
        return [
            [
                'code' => Payment::GATEWAY_GCASH,
                'name' => 'GCash',
                'icon' => 'gcash.png',
                'description' => 'Pay with GCash e-wallet',
            ],
            [
                'code' => Payment::GATEWAY_MAYA,
                'name' => 'Maya',
                'icon' => 'maya.png',
                'description' => 'Pay with Maya e-wallet',
            ],
            [
                'code' => Payment::GATEWAY_CREDIT_CARD,
                'name' => 'Credit/Debit Card',
                'icon' => 'card.png',
                'description' => 'Visa, Mastercard, JCB',
            ],
            [
                'code' => Payment::GATEWAY_COD,
                'name' => 'Cash on Delivery',
                'icon' => 'cod.png',
                'description' => 'Pay when you receive your order',
            ],
        ];
    }
}

<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\ProductVariant;
use App\Entity\PromoCode;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private const TAX_RATE = '0.12'; // 12% VAT in Philippines

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
        private PromoCodeRepository $promoCodeRepository,
        private RequestStack $requestStack,
        private InventoryService $inventoryService
    ) {}

    public function getCart(?User $user = null): Cart
    {
        $cart = null;

        if ($user) {
            $cart = $this->cartRepository->findByUser($user);
        }

        if (!$cart) {
            $sessionId = $this->getSessionId();
            if ($sessionId) {
                $cart = $this->cartRepository->findBySessionId($sessionId);
            }
        }

        if (!$cart) {
            $cart = new Cart();
            if ($user) {
                $cart->setUser($user);
            } else {
                $sessionId = $this->getSessionId();
                // Only set session ID if it's not empty
                if ($sessionId && $sessionId !== '') {
                    $cart->setSessionId($sessionId);
                } else {
                    // Generate a unique session ID if none exists
                    $cart->setSessionId('guest_' . bin2hex(random_bytes(16)));
                }
            }
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }

    public function addItem(Cart $cart, ProductVariant $variant, int $quantity = 1, bool $reserveStock = true): CartItem
    {
        $existingItem = $cart->findItemByVariant($variant);
        $requiredQuantity = $quantity;

        if ($reserveStock && !$this->inventoryService->reserveStock($variant, $requiredQuantity)) {
            throw new \InvalidArgumentException('Not enough stock available');
        }

        if ($existingItem) {
            $existingItem->incrementQuantity($quantity);
            $this->entityManager->flush();
            return $existingItem;
        }

        $item = new CartItem();
        $item->setVariant($variant);
        $item->setQuantity($quantity);
        $item->setUnitPrice($variant->getPrice());
        $cart->addItem($item);

        $this->entityManager->flush();
        return $item;
    }

    public function updateItemQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($item);
            return;
        }

        $currentQuantity = $item->getQuantity();
        $delta = $quantity - $currentQuantity;

        if ($delta > 0 && !$this->inventoryService->reserveStock($item->getVariant(), $delta)) {
            throw new \InvalidArgumentException('Not enough stock available');
        }

        if ($delta < 0) {
            $this->inventoryService->releaseReservedStock($item->getVariant(), abs($delta));
        }

        $item->setQuantity($quantity);
        $this->entityManager->flush();
    }

    public function removeItem(CartItem $item): void
    {
        $cart = $item->getCart();
        if ($item->getVariant()) {
            $this->inventoryService->releaseReservedStock($item->getVariant(), $item->getQuantity());
        }
        $cart->removeItem($item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    public function clearCart(Cart $cart, bool $releaseReservedStock = true): void
    {
        foreach (iterator_to_array($cart->getItems(), false) as $item) {
            if ($releaseReservedStock && $item->getVariant()) {
                $this->inventoryService->releaseReservedStock($item->getVariant(), $item->getQuantity());
            }

            $cart->removeItem($item);
            $this->entityManager->remove($item);
        }

        $cart->setPromoCode(null);
        $this->entityManager->flush();
    }

    public function applyPromoCode(Cart $cart, string $code): array
    {
        $promoCode = $this->promoCodeRepository->findByCode($code);

        if (!$promoCode) {
            return ['success' => false, 'error' => 'Invalid promo code'];
        }

        if (!$promoCode->isValid()) {
            return ['success' => false, 'error' => 'This promo code is no longer valid'];
        }

        $subtotal = $cart->getSubtotal();
        if ($promoCode->getMinimumOrderAmount() && bccomp($subtotal, $promoCode->getMinimumOrderAmount(), 2) < 0) {
            return [
                'success' => false,
                'error' => sprintf('Minimum order of ₱%s required', number_format($promoCode->getMinimumOrderAmount(), 2))
            ];
        }

        $cart->setPromoCode($promoCode);
        $this->entityManager->flush();

        return [
            'success' => true,
            'discount' => $promoCode->calculateDiscount($subtotal),
            'message' => 'Promo code applied successfully'
        ];
    }

    public function removePromoCode(Cart $cart): void
    {
        $cart->setPromoCode(null);
        $this->entityManager->flush();
    }

    public function getCartSummary(Cart $cart): array
    {
        $subtotal = $cart->getSubtotal();
        $discount = $cart->getDiscount();
        $taxableAmount = bcsub($subtotal, $discount, 2);
        $tax = bcmul($taxableAmount, self::TAX_RATE, 2);
        $total = bcadd($taxableAmount, $tax, 2);

        return [
            'item_count' => $cart->getItemCount(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'tax_rate' => self::TAX_RATE,
            'total' => $total,
            'promo_code' => $cart->getPromoCode()?->getCode(),
            'requires_age_verification' => $cart->requiresAgeVerification(),
        ];
    }

    public function mergeGuestCart(User $user): void
    {
        $sessionId = $this->getSessionId();
        if (!$sessionId) {
            return;
        }

        $guestCart = $this->cartRepository->findBySessionId($sessionId);
        if (!$guestCart || $guestCart->isEmpty()) {
            return;
        }

        $userCart = $this->cartRepository->findByUser($user);
        
        if (!$userCart) {
            $guestCart->setUser($user);
            $guestCart->setSessionId(null);
            $this->entityManager->flush();
            return;
        }

        // Merge items
        foreach ($guestCart->getItems() as $guestItem) {
            $existingItem = $userCart->findItemByVariant($guestItem->getVariant());
            if ($existingItem) {
                $existingItem->incrementQuantity($guestItem->getQuantity());
            } else {
                $newItem = new CartItem();
                $newItem->setVariant($guestItem->getVariant());
                $newItem->setQuantity($guestItem->getQuantity());
                $newItem->setUnitPrice($guestItem->getUnitPrice());
                $userCart->addItem($newItem);
            }
        }

        // Remove guest cart
        $this->entityManager->remove($guestCart);
        $this->entityManager->flush();
    }

    private function getSessionId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return null;
        }
        return $request->getSession()->getId();
    }

    public function validateCartItems(Cart $cart): array
    {
        $errors = [];

        foreach ($cart->getItems() as $item) {
            $variant = $item->getVariant();
            
            if (!$variant->isActive() || !$variant->getProduct()->isActive()) {
                $errors[] = [
                    'item' => $item,
                    'error' => sprintf('%s is no longer available', $variant->getDisplayName())
                ];
                continue;
            }

            if (!$item->isInStock()) {
                $available = $variant->getAvailableStock();
                if ($available <= 0) {
                    $errors[] = [
                        'item' => $item,
                        'error' => sprintf('%s is out of stock', $variant->getDisplayName())
                    ];
                } else {
                    $errors[] = [
                        'item' => $item,
                        'error' => sprintf('Only %d units of %s available', $available, $variant->getDisplayName())
                    ];
                }
            }
        }

        return $errors;
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductVariantRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
        private ProductVariantRepository $variantRepository,
        private CategoryRepository $categoryRepository
    ) {}

    #[Route('', name: 'cart_index')]
    public function index(): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('warning', 'Please log in to access your cart.');
            return $this->redirectToRoute('app_login');
        }

        $cart = $this->cartService->getCart($this->getUser());
        $summary = $this->cartService->getCartSummary($cart);
        $errors = $this->cartService->validateCartItems($cart);

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'summary' => $summary,
            'errors' => $errors,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/add', name: 'cart_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        if ($response = $this->denyGuestCartApiAccess()) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        $variantId = $data['variant_id'] ?? null;
        $quantity = max(1, (int)($data['quantity'] ?? 1));

        if (!$variantId) {
            return new JsonResponse(['success' => false, 'error' => 'No variant specified'], 400);
        }

        $variant = $this->variantRepository->find($variantId);
        if (!$variant) {
            return new JsonResponse(['success' => false, 'error' => 'Product not found'], 404);
        }

        if (!$variant->isActive() || !$variant->getProduct()->isActive()) {
            return new JsonResponse(['success' => false, 'error' => 'Product is not available'], 400);
        }

        if ($variant->getAvailableStock() < $quantity) {
            return new JsonResponse(['success' => false, 'error' => 'Not enough stock available'], 400);
        }

        $cart = $this->cartService->getCart($this->getUser());

        try {
            $this->cartService->addItem($cart, $variant, $quantity);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }

        $cartHtml = $this->renderView('customer/_partials/mini_cart.html.twig', [
            'cart' => $cart,
        ]);

        return new JsonResponse([
            'success' => true,
            'cart_count' => $cart->getItemCount(),
            'cart_html' => $cartHtml,
            'message' => 'Product added to cart'
        ]);
    }

    #[Route('/update', name: 'cart_update', methods: ['POST'])]
    public function update(Request $request): JsonResponse
    {
        if ($response = $this->denyGuestCartApiAccess()) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        $itemId = $data['item_id'] ?? null;
        $quantity = max(0, (int)($data['quantity'] ?? 0));

        $cart = $this->cartService->getCart($this->getUser());
        $item = null;

        foreach ($cart->getItems() as $cartItem) {
            if ($cartItem->getId() == $itemId) {
                $item = $cartItem;
                break;
            }
        }

        if (!$item) {
            return new JsonResponse(['success' => false, 'error' => 'Item not found'], 404);
        }

        if ($quantity === 0) {
            $this->cartService->removeItem($item);
        } else {
            try {
                $this->cartService->updateItemQuantity($item, $quantity);
            } catch (\InvalidArgumentException $e) {
                return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
            }
        }

        $cart = $this->cartService->getCart($this->getUser());
        $summary = $this->cartService->getCartSummary($cart);

        return new JsonResponse([
            'success' => true,
            'cart_count' => $cart->getItemCount(),
            'summary' => $summary,
            'cart_html' => $this->renderView('customer/_partials/mini_cart.html.twig', ['cart' => $cart]),
        ]);
    }

    #[Route('/remove', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request): JsonResponse
    {
        if ($response = $this->denyGuestCartApiAccess()) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        $itemId = $data['item_id'] ?? null;

        $cart = $this->cartService->getCart($this->getUser());
        
        foreach ($cart->getItems() as $item) {
            if ($item->getId() == $itemId) {
                $this->cartService->removeItem($item);
                break;
            }
        }

        $cart = $this->cartService->getCart($this->getUser());
        $summary = $this->cartService->getCartSummary($cart);

        return new JsonResponse([
            'success' => true,
            'cart_count' => $cart->getItemCount(),
            'summary' => $summary,
            'cart_html' => $this->renderView('customer/_partials/mini_cart.html.twig', ['cart' => $cart]),
        ]);
    }

    #[Route('/promo', name: 'cart_apply_promo', methods: ['POST'])]
    public function applyPromo(Request $request): JsonResponse
    {
        if ($response = $this->denyGuestCartApiAccess()) {
            return $response;
        }

        $data = json_decode($request->getContent(), true);
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            return new JsonResponse(['success' => false, 'error' => 'Please enter a promo code'], 400);
        }

        $cart = $this->cartService->getCart($this->getUser());
        $result = $this->cartService->applyPromoCode($cart, $code);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        $summary = $this->cartService->getCartSummary($cart);

        return new JsonResponse([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'summary' => $summary,
        ]);
    }

    #[Route('/promo/remove', name: 'cart_remove_promo', methods: ['POST'])]
    public function removePromo(): JsonResponse
    {
        if ($response = $this->denyGuestCartApiAccess()) {
            return $response;
        }

        $cart = $this->cartService->getCart($this->getUser());
        $this->cartService->removePromoCode($cart);
        
        $summary = $this->cartService->getCartSummary($cart);

        return new JsonResponse([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    private function denyGuestCartApiAccess(): ?JsonResponse
    {
        if ($this->getUser()) {
            return null;
        }

        return new JsonResponse([
            'success' => false,
            'error' => 'Please log in to add items to your cart.',
            'redirect' => $this->generateUrl('app_login'),
        ], 401);
    }

    private function denyAdminShoppingApiAccess(): ?JsonResponse
    {
        if (!$this->isGranted(User::ROLE_ADMIN)) {
            return null;
        }

        return new JsonResponse([
            'success' => false,
            'error' => 'Admin account cannot shop. Please use a customer account for purchases.',
            'redirect' => $this->generateUrl('admin_dashboard'),
        ], 403);
    }
}

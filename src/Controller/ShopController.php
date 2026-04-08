<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/shop')]
class ShopController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private ProductVariantRepository $variantRepository,
        private CartService $cartService
    ) {}

    #[Route('', name: 'shop_index')]
    public function index(Request $request): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $sort = $request->query->get('sort', 'name');
        
        $products = $this->productRepository->findActive();
        
        // Sort products
        usort($products, function($a, $b) use ($sort) {
            return match($sort) {
                'price_asc' => bccomp($a->getBasePrice(), $b->getBasePrice()),
                'price_desc' => bccomp($b->getBasePrice(), $a->getBasePrice()),
                'newest' => $b->getCreatedAt() <=> $a->getCreatedAt(),
                default => strcmp($a->getName(), $b->getName()),
            };
        });
        
        $total = count($products);
        $products = array_slice($products, ($page - 1) * $limit, $limit);
        $totalPages = ceil($total / $limit);

        return $this->render('shop/index.html.twig', [
            'products' => $products,
            'categories' => $this->categoryRepository->findActive(),
            'categories_menu' => $this->categoryRepository->findActive(),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'sort' => $sort,
            'cart' => $this->cartService->getCart($this->getUser()),
            'cart_count' => $this->cartService->getCart($this->getUser())->getItemCount(),
        ]);
    }

    #[Route('/category/{slug}', name: 'shop_category')]
    public function category(string $slug, Request $request): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        $category = $this->categoryRepository->findBySlug($slug);
        
        if (!$category || !$category->isActive()) {
            throw $this->createNotFoundException('Category not found');
        }
        
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        $sort = $request->query->get('sort', 'name');
        
        $products = $this->productRepository->findByCategory($category);
        
        // Sort products
        usort($products, function($a, $b) use ($sort) {
            return match($sort) {
                'price_asc' => bccomp($a->getBasePrice(), $b->getBasePrice()),
                'price_desc' => bccomp($b->getBasePrice(), $a->getBasePrice()),
                'newest' => $b->getCreatedAt() <=> $a->getCreatedAt(),
                default => strcmp($a->getName(), $b->getName()),
            };
        });
        
        $total = count($products);
        $products = array_slice($products, ($page - 1) * $limit, $limit);
        $totalPages = ceil($total / $limit);

        return $this->render('shop/category.html.twig', [
            'category' => $category,
            'products' => $products,
            'categories' => $this->categoryRepository->findActive(),
            'categories_menu' => $this->categoryRepository->findActive(),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'sort' => $sort,
            'cart' => $this->cartService->getCart($this->getUser()),
            'cart_count' => $this->cartService->getCart($this->getUser())->getItemCount(),
        ]);
    }

    #[Route('/product/{slug}', name: 'shop_product')]
    public function show(string $slug): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        $product = $this->productRepository->findBySlug($slug);
        
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Product not found');
        }
        
        $variants = $this->variantRepository->findByProduct($product);
        
        // Get related products from same category
        $relatedProducts = array_slice(
            array_filter(
                $this->productRepository->findByCategory($product->getCategory()),
                fn($p) => $p->getId() !== $product->getId()
            ),
            0,
            4
        );

        return $this->render('shop/show.html.twig', [
            'product' => $product,
            'variants' => $variants,
            'related_products' => $relatedProducts,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart' => $this->cartService->getCart($this->getUser()),
            'cart_count' => $this->cartService->getCart($this->getUser())->getItemCount(),
        ]);
    }

    #[Route('/search', name: 'shop_search')]
    public function search(Request $request): Response
    {
        if ($response = $this->denyAdminShoppingAccess()) {
            return $response;
        }

        $query = trim($request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;
        
        $products = [];
        $total = 0;
        
        if (strlen($query) >= 2) {
            $products = $this->productRepository->search($query);
            $total = count($products);
            $products = array_slice($products, ($page - 1) * $limit, $limit);
        }
        
        $totalPages = ceil($total / $limit);

        return $this->render('shop/search.html.twig', [
            'query' => $query,
            'products' => $products,
            'categories_menu' => $this->categoryRepository->findActive(),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $total,
            'cart' => $this->cartService->getCart($this->getUser()),
            'cart_count' => $this->cartService->getCart($this->getUser())->getItemCount(),
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

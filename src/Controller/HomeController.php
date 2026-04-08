<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private CartService $cartService
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findActive();
        $featuredProducts = array_slice($this->productRepository->findActive(), 0, 8);
        $cart = $this->cartService->getCart($this->getUser());

        return $this->render('home/index.html.twig', [
            'categories' => $categories,
            'categories_menu' => $categories,
            'featured_products' => $featuredProducts,
            'cart' => $cart,
            'cart_count' => $cart->getItemCount(),
        ]);
    }

    #[Route('/about', name: 'page_about')]
    public function about(): Response
    {
        $categories = $this->categoryRepository->findActive();
        $cart = $this->cartService->getCart($this->getUser());

        // Team members data
        $team = [
            [
                'name' => 'Juan Dela Cruz',
                'role' => 'Founder & CEO',
                'bio' => 'Vaping enthusiast since 2015. Juan founded VapeShop with a mission to provide quality products to Filipino vapers.',
                'image' => null,
            ],
            [
                'name' => 'Maria Santos',
                'role' => 'Operations Manager',
                'bio' => 'Ensures smooth order fulfillment and customer satisfaction. 5+ years in e-commerce logistics.',
                'image' => null,
            ],
            [
                'name' => 'Miguel Reyes',
                'role' => 'Product Specialist',
                'bio' => 'Our go-to expert for product recommendations. Tests and curates our entire catalog.',
                'image' => null,
            ],
            [
                'name' => 'Ana Garcia',
                'role' => 'Customer Support Lead',
                'bio' => 'Dedicated to helping customers find the perfect vape. Available 24/7 to assist.',
                'image' => null,
            ],
        ];

        return $this->render('home/about.html.twig', [
            'categories_menu' => $categories,
            'cart' => $cart,
            'cart_count' => $cart->getItemCount(),
            'team' => $team,
        ]);
    }

    #[Route('/contact', name: 'page_contact')]
    public function contact(Request $request): Response
    {
        $categories = $this->categoryRepository->findActive();
        $cart = $this->cartService->getCart($this->getUser());

        // Handle form submission (via FormSubmit service, but we also track it)
        $submitted = $request->query->get('submitted') === '1';

        return $this->render('home/contact.html.twig', [
            'categories_menu' => $categories,
            'cart' => $cart,
            'cart_count' => $cart->getItemCount(),
            'submitted' => $submitted,
        ]);
    }
}

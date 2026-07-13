<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        $brandImages = [
            'uploads/products/a.jpg',
            'uploads/products/b.jpg',
            'uploads/products/c.jpg',
            'uploads/products/d.jpg',
            'uploads/products/e.jpg',
            'uploads/products/f.jpg',
            'uploads/products/g.jpg',
            'uploads/products/h.jpg',
            'uploads/products/i.jpg',
            'uploads/products/j.jpg',
            'uploads/products/k.jpg',
            'uploads/products/logo.jpg',
        ];

        return $this->render('home/index.html.twig', [
            'categories' => $categories,
            'categories_menu' => $categories,
            'featured_products' => $featuredProducts,
            'brand_images' => $brandImages,
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
                'name' => 'Justine Preciado',
                'role' => 'Founder & CEO',
                'bio' => 'Vaping enthusiast and tech innovator. Justine founded Easy Vape to revolutionize how Filipinos access quality vaping products with seamless mobile experience.',
                'image' => null,
            ],
            [
                'name' => 'Alex Rivera',
                'role' => 'Mobile App Lead',
                'bio' => 'Full-stack developer building the Preciado mobile app. Focused on delivering an intuitive, fast, and secure shopping experience.',
                'image' => null,
            ],
            [
                'name' => 'Sarah Lopez',
                'role' => 'Product & UX Manager',
                'bio' => 'Ensures seamless product experience across web and mobile. Passionate about user-centered design and customer insights.',
                'image' => null,
            ],
            [
                'name' => 'Carlo Fernandez',
                'role' => 'Operations & Customer Care',
                'bio' => 'Dedicated to excellence in fulfillment and support. Ensures every customer receives the best service possible.',
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

    #[Route('/healthz', name: 'app_healthz')]
    public function healthz(): Response
    {
        return new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }
}

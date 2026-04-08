<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * GET /api/products
     * Returns list of products with optional pagination
     * Public endpoint - no auth required
     */
    #[Route('/products', name: 'products', methods: ['GET'])]
    public function products(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $category = $request->query->get('category');

        $products = $this->productRepository->findActive();
        
        // Filter by category if provided
        if ($category) {
            $products = array_filter($products, fn($p) => 
                $p->getCategory()?->getSlug() === $category
            );
            $products = array_values($products);
        }

        $total = count($products);
        $totalPages = (int) ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $products = array_slice($products, $offset, $limit);

        $data = [];
        foreach ($products as $product) {
            $variants = [];
            foreach ($product->getVariants() as $variant) {
                if ($variant->isActive()) {
                    $variants[] = [
                        'id' => $variant->getId(),
                        'sku' => $variant->getSku(),
                        'name' => $variant->getName(),
                        'price' => $variant->getPrice(),
                        'comparePrice' => $variant->getComparePrice(),
                        'stock' => $variant->getStockQuantity(),
                        'inStock' => $variant->isInStock(),
                    ];
                }
            }

            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'description' => $product->getDescription(),
                'shortDescription' => $product->getShortDescription(),
                'basePrice' => $product->getBasePrice(),
                'category' => $product->getCategory()?->getName(),
                'categorySlug' => $product->getCategory()?->getSlug(),
                'requiresAgeVerification' => $product->isRequiresAgeVerification(),
                'isFeatured' => $product->isFeatured(),
                'variants' => $variants,
                'createdAt' => $product->getCreatedAt()?->format('c'),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * GET /api/orders
     * Returns list of orders (admin only)
     */
    #[Route('/orders', name: 'orders', methods: ['GET'])]
    public function orders(Request $request): JsonResponse
    {
        // Check authorization - require admin role
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'error' => 'Unauthorized. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $orders = $this->orderRepository->findBy($criteria, ['createdAt' => 'DESC']);
        
        $total = count($orders);
        $totalPages = (int) ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $orders = array_slice($orders, $offset, $limit);

        $data = [];
        foreach ($orders as $order) {
            $items = [];
            foreach ($order->getItems() as $item) {
                $items[] = [
                    'id' => $item->getId(),
                    'productName' => $item->getProductName(),
                    'variantName' => $item->getVariantName(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'total' => $item->getTotal(),
                ];
            }

            $data[] = [
                'id' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'status' => $order->getStatus(),
                'customer' => [
                    'id' => $order->getUser()?->getId(),
                    'name' => $order->getUser()?->getFullName(),
                    'email' => $order->getUser()?->getEmail(),
                ],
                'items' => $items,
                'subtotal' => $order->getSubtotal(),
                'shippingCost' => $order->getShippingCost(),
                'taxAmount' => $order->getTaxAmount(),
                'discountAmount' => $order->getDiscountAmount(),
                'total' => $order->getTotal(),
                'paymentMethod' => $order->getPaymentMethod(),
                'paymentStatus' => $order->getPaymentStatus(),
                'shippingMethod' => $order->getShippingMethod(),
                'createdAt' => $order->getCreatedAt()?->format('c'),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * GET /api/users
     * Returns list of users (admin only)
     */
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        // Check authorization - require admin role
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'success' => false,
                'error' => 'Unauthorized. Admin access required.',
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));
        $role = $request->query->get('role');

        $users = $this->userRepository->findBy([], ['createdAt' => 'DESC']);
        
        // Filter by role if provided
        if ($role) {
            $users = array_filter($users, fn($u) => in_array($role, $u->getRoles()));
            $users = array_values($users);
        }

        $total = count($users);
        $totalPages = (int) ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        $users = array_slice($users, $offset, $limit);

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'phone' => $user->getPhone(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'isEmailVerified' => $user->isEmailVerified(),
                'ageVerificationStatus' => $user->getAgeVerificationStatus(),
                'loyaltyPoints' => $user->getLoyaltyPoints(),
                'createdAt' => $user->getCreatedAt()?->format('c'),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * POST /api/register
     * Register a new user via API (triggers email verification)
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $required = ['email', 'password', 'firstName', 'lastName'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'error' => "Field '$field' is required.",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'error' => 'Email already registered.',
            ], Response::HTTP_CONFLICT);
        }

        // Create new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setPhone($data['phone'] ?? null);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setRoles([User::ROLE_CUSTOMER]);
        $user->setIsEmailVerified(false);
        $user->generateEmailVerificationToken();

        // Validate entity
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'error' => 'Validation failed.',
                'details' => $errorMessages,
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($user);
        $this->em->flush();

        // Generate verification URL
        $verificationUrl = $this->generateUrl('verify_email', [
            'token' => $user->getEmailVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // In production, send email here via mailer service
        // For now, return the verification URL in the response (demo mode)

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email.',
            'data' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'isEmailVerified' => $user->isEmailVerified(),
            ],
            // Demo mode - include verification URL (remove in production)
            'verificationUrl' => $verificationUrl,
        ], Response::HTTP_CREATED);
    }
}

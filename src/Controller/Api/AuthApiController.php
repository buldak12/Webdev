<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/auth')]
class AuthApiController extends AbstractController
{
    /**
     * Register a new customer
     * POST /api/auth/register
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate input
        if (!isset($data['email'], $data['password'], $data['first_name'], $data['last_name'])) {
            return $this->json(
                ['error' => 'Missing required fields: email, password, first_name, last_name'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Check if email exists
        if ($userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json(
                ['error' => 'Email already registered'],
                Response::HTTP_CONFLICT
            );
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['first_name']);
        $user->setLastName($data['last_name']);
        $user->setPhone($data['phone'] ?? null);
        $user->setRoles([User::ROLE_CUSTOMER]);
        $user->setIsActive(true);
        $user->setPassword($passwordHasher->hashPassword($user, $data['password']));
        $user->setAgeVerificationStatus(User::AGE_STATUS_PENDING);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'User registered successfully',
            'user' => $this->serializeUser($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Login with email and password
     * POST /api/auth/login
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return $this->json(
                ['error' => 'Missing email or password'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json(
                ['error' => 'Invalid credentials'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (!$user->isActive()) {
            return $this->json(
                ['error' => 'Account is inactive'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Generate a simple token (in production, use JWT)
        $token = bin2hex(random_bytes(32));
        
        return $this->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Get current user profile (requires token in header)
     * GET /api/auth/me
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function getProfile(UserRepository $userRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->json($this->serializeUser($user));
    }

    /**
     * Update user profile
     * PUT /api/auth/me
     */
    #[Route('/me', name: 'api_auth_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(
                ['error' => 'Unauthorized'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $data = json_decode($request->getContent(), true);

        // Update allowed fields
        if (isset($data['first_name'])) {
            $user->setFirstName($data['first_name']);
        }
        if (isset($data['last_name'])) {
            $user->setLastName($data['last_name']);
        }
        if (isset($data['phone'])) {
            $user->setPhone($data['phone']);
        }

        $em->flush();

        return $this->json([
            'message' => 'Profile updated',
            'user' => $this->serializeUser($user),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'phone' => $user->getPhone(),
            'roles' => $user->getRoles(),
            'loyalty_points' => $user->getLoyaltyPoints(),
            'age_verification_status' => $user->getAgeVerificationStatus(),
            'is_active' => $user->isActive(),
            'created_at' => $user->getCreatedAt()?->format('c'),
        ];
    }
}

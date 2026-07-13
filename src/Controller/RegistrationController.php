<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\CartService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UserRepository $userRepository,
        private CategoryRepository $categoryRepository,
        private CartService $cartService,
        private EmailService $emailService,
        private ?LoggerInterface $logger = null
    ) {}

    #[Route('/register', name: 'app_register')]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('account_index');
        }

        $form = $this->createForm(RegistrationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            // Check if email exists
            $existing = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existing) {
                $this->addFlash('error', 'An account with this email already exists');
                return $this->redirectToRoute('app_register');
            }
            
            // Create user
            $user = new User();
            $user->setFirstName($data['firstName']);
            $user->setLastName($data['lastName']);
            $user->setEmail($data['email']);
            $user->setRoles([User::ROLE_CUSTOMER]);
            $user->setIsEmailVerified(false);
            $user->generateEmailVerificationToken();
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['plainPassword']);
            $user->setPassword($hashedPassword);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            // Send verification email
            try {
                $this->emailService->sendVerificationEmail($user);
                $this->addFlash('success', 'Account created! We\'ve sent a verification link to your email address.');
            } catch (\Exception $e) {
                // If email fails, show the link directly (fallback for development)
                $this->logger?->error('Failed to send verification email', ['error' => $e->getMessage()]);
                $verificationUrl = $this->generateUrl('verify_email', [
                    'token' => $user->getEmailVerificationToken(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->addFlash('success', 'Account created! Please verify your email to continue.');
                $this->addFlash('info', 'Email could not be sent. <a href="' . $verificationUrl . '">Click here to verify</a>');
            }
            
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => 0,
        ]);
    }
}

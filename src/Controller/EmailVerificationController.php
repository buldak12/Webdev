<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EmailVerificationController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em
    ) {}

    #[Route('/verify-email/{token}', name: 'verify_email')]
    public function verifyEmail(string $token): Response
    {
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Invalid or expired verification link.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('info', 'Your email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        // Verify the email
        $user->setIsEmailVerified(true);
        $user->setEmailVerificationToken(null); // Clear token after use
        $this->em->flush();

        $this->addFlash('success', 'Email verified successfully! You can now log in.');
        
        return $this->redirectToRoute('app_login');
    }

    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $email = $request->request->get('email');
        
        if (!$email) {
            $this->addFlash('error', 'Please provide an email address.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            // Don't reveal if email exists
            $this->addFlash('success', 'If that email exists, a verification link has been sent.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('info', 'This email is already verified.');
            return $this->redirectToRoute('app_login');
        }

        // Generate new token
        $user->generateEmailVerificationToken();
        $this->em->flush();

        // In production, send email here
        // For demo, just show success
        $this->addFlash('success', 'Verification email sent. Please check your inbox.');

        return $this->redirectToRoute('app_login');
    }
}

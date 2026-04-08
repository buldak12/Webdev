<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Google OAuth authenticator for CUSTOMER login/registration.
 * 
 * This authenticator:
 * 1. Allows customers to login OR register via Google
 * 2. Creates a new account if user doesn't exist
 * 3. Auto-verifies email since it comes from Google
 */
class CustomerGoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private ?LoggerInterface $logger = null
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'oauth_google_customer_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google_customer');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                $email = $googleUser->getEmail();

                // Find existing user by email
                $user = $this->userRepository->findOneBy(['email' => $email]);

                if ($user) {
                    // Existing user - auto-verify email if not already
                    if (!$user->isEmailVerified()) {
                        $user->setIsEmailVerified(true);
                        $user->setEmailVerificationToken(null);
                        $user->setEmailVerifiedAt(new \DateTime());
                        $this->em->flush();
                    }
                    return $user;
                }

                // Create new customer account
                $user = new User();
                $user->setEmail($email);
                $user->setFirstName($googleUser->getFirstName() ?: 'Customer');
                $user->setLastName($googleUser->getLastName() ?: '');
                $user->setRoles([User::ROLE_CUSTOMER]);
                $user->setIsEmailVerified(true); // Google verified the email
                $user->setEmailVerifiedAt(new \DateTime());
                $user->setGoogleId($googleUser->getId());
                
                // Set a random password (user won't need it for Google login)
                $user->setPassword(bin2hex(random_bytes(32)));

                $this->em->persist($user);
                $this->em->flush();

                // Send welcome email
                try {
                    $this->emailService->sendWelcomeEmail($user);
                } catch (\Exception $e) {
                    $this->logger?->error('Failed to send welcome email', ['error' => $e->getMessage()]);
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Store success message
        $request->getSession()->getFlashBag()->add('success', 'Welcome! You are now signed in.');
        
        // Redirect to account or homepage
        return new RedirectResponse($this->router->generate('account_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}

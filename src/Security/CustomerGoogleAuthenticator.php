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
 * 1. Allows Google users to login OR register
 * 2. Assigns all Google users to ROLE_STAFF
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
        $route = $request->attributes->get('_route');
        $flow = $request->getSession()->get('oauth_login_flow');

        // Backward compatible with the old callback route, but prefer unified callback route.
        return $route === 'oauth_google_customer_check' || ($route === 'oauth_google_check' && $flow === 'customer');
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

                if (!$user) {
                    // Create new staff account for Google users.
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($googleUser->getFirstName() ?: 'Staff');
                    $user->setLastName($googleUser->getLastName() ?: 'User');

                    // Set a random password for fallback local auth.
                    $user->setPassword(bin2hex(random_bytes(32)));
                    $this->em->persist($user);

                    // Send welcome email for newly created accounts.
                    try {
                        $this->emailService->sendWelcomeEmail($user);
                    } catch (\Exception $e) {
                        $this->logger?->error('Failed to send welcome email', ['error' => $e->getMessage()]);
                    }
                }

                $user->setRoles([User::ROLE_STAFF]);
                $user->setIsActive(true);
                $user->setIsEmailVerified(true);
                $user->setEmailVerificationToken(null);
                $user->setEmailVerifiedAt(new \DateTime());
                $user->setGoogleId($googleUser->getId());

                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->getSession()->remove('oauth_login_flow');

        // Store success message
        $request->getSession()->getFlashBag()->add('success', 'Welcome! You are now signed in.');
        
        // Redirect all Google users to the staff dashboard.
        return new RedirectResponse($this->router->generate('staff_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->remove('oauth_login_flow');
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}

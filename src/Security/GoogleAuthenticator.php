<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Google OAuth authenticator for STAFF login only.
 * 
 * This authenticator:
 * 1. Allows Google users to sign in through staff flow
 * 2. Ensures Google users are assigned ROLE_STAFF
 * 3. Auto-verifies email on successful OAuth login
 */
class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $em,
        private RouterInterface $router,
        private UserRepository $userRepository
    ) {}

    public function supports(Request $request): ?bool
    {
        $route = $request->attributes->get('_route');
        $flow = $request->getSession()->get('oauth_login_flow');

        return $route === 'oauth_google_check' && $flow !== 'customer';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                $email = $googleUser->getEmail();

                // Find existing user by email
                $user = $this->userRepository->findOneBy(['email' => $email]);

                if (!$user) {
                    // Auto-provision new Google users as staff accounts.
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName($googleUser->getFirstName() ?: 'Staff');
                    $user->setLastName($googleUser->getLastName() ?: 'User');
                    $user->setPassword(bin2hex(random_bytes(32)));
                    // Only set ROLE_STAFF on brand-new accounts — never overwrite existing roles.
                    $user->setRoles([User::ROLE_STAFF]);
                    $this->em->persist($user);
                }

                // Never downgrade an existing user's roles (e.g. ROLE_ADMIN → ROLE_STAFF).
                $user->setIsActive(true);
                $user->setGoogleId($googleUser->getId());

                // Auto-verify email on OAuth login
                if (!$user->isEmailVerified()) {
                    $user->setIsEmailVerified(true);
                    $user->setEmailVerificationToken(null);
                }
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->getSession()->remove('oauth_login_flow');

        return new RedirectResponse($this->router->generate('staff_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->remove('oauth_login_flow');
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());
        return new RedirectResponse($this->router->generate('staff_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('staff_login'));
    }
}

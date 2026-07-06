<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleOAuthController extends AbstractController
{
    private function getGoogleRedirectUri(Request $request): string
    {
        $configuredBaseUri = (string) ($_SERVER['GOOGLE_OAUTH_REDIRECT_BASE_URI'] ?? $_ENV['GOOGLE_OAUTH_REDIRECT_BASE_URI'] ?? '');
        $baseUri = rtrim($configuredBaseUri, '/');

        // Fallback to current request host when env is not defined.
        if ($baseUri === '') {
            $baseUri = $request->getSchemeAndHttpHost();
        }

        return $baseUri . $this->generateUrl('oauth_google_check');
    }

    /**
     * Staff Google OAuth - connects to existing staff accounts only
     */
    #[Route('/oauth/google/connect', name: 'oauth_google_connect')]
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        $request->getSession()->set('oauth_login_flow', 'staff');
        $redirectUri = $this->getGoogleRedirectUri($request);

        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'openid',
                'email', 
                'profile',
            ], [
                'redirect_uri' => $redirectUri,
                'prompt' => 'select_account',
            ]);
    }

    #[Route('/oauth/google/check', name: 'oauth_google_check')]
    public function check(Request $request): void
    {
        // This route is handled by the GoogleAuthenticator (staff)
        throw new \LogicException('This route should be handled by the authenticator.');
    }

    /**
     * Customer Google OAuth - creates new accounts or logs in existing customers
     */
    #[Route('/oauth/google/customer/connect', name: 'oauth_google_customer_connect')]
    public function customerConnect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        $request->getSession()->set('oauth_login_flow', 'customer');
        $redirectUri = $this->getGoogleRedirectUri($request);

        return $clientRegistry
            ->getClient('google_customer')
            ->redirect([
                'openid',
                'email', 
                'profile',
            ], [
                'redirect_uri' => $redirectUri,
                'prompt' => 'select_account',
            ]);
    }

    #[Route('/oauth/google/customer/check', name: 'oauth_google_customer_check')]
    public function customerCheck(Request $request): void
    {
        // This route is handled by the CustomerGoogleAuthenticator
        throw new \LogicException('This route should be handled by the authenticator.');
    }
}

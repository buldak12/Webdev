<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GoogleOAuthController extends AbstractController
{
    /**
     * Staff Google OAuth - connects to existing staff accounts only
     */
    #[Route('/oauth/google/connect', name: 'oauth_google_connect')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'openid',
                'email', 
                'profile',
            ], []);
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
    public function customerConnect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google_customer')
            ->redirect([
                'openid',
                'email', 
                'profile',
            ], []);
    }

    #[Route('/oauth/google/customer/check', name: 'oauth_google_customer_check')]
    public function customerCheck(Request $request): void
    {
        // This route is handled by the CustomerGoogleAuthenticator
        throw new \LogicException('This route should be handled by the authenticator.');
    }
}

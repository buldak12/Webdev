<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
        private string $fromEmail = 'noreply@vapeshop.ph',
        private string $fromName = 'VapeShop PH'
    ) {}

    public function sendVerificationEmail(User $user): void
    {
        $verificationUrl = $this->urlGenerator->generate('verify_email', [
            'token' => $user->getEmailVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $html = $this->twig->render('emails/verification.html.twig', [
            'user' => $user,
            'verificationUrl' => $verificationUrl,
        ]);

        $email = (new Email())
            ->from($this->fromName . ' <' . $this->fromEmail . '>')
            ->to($user->getEmail())
            ->subject('Verify your email address - VapeShop PH')
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendWelcomeEmail(User $user): void
    {
        $html = $this->twig->render('emails/welcome.html.twig', [
            'user' => $user,
        ]);

        $email = (new Email())
            ->from($this->fromName . ' <' . $this->fromEmail . '>')
            ->to($user->getEmail())
            ->subject('Welcome to VapeShop PH!')
            ->html($html);

        $this->mailer->send($email);
    }
}

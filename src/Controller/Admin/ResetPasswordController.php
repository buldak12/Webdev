<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/admin')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params
    ) {}

    #[Route('/reset-password', name: 'admin_reset_password', methods: ['GET','POST'])]
    public function reset(Request $request): Response
    {
        $configuredToken = $this->params->get('ADMIN_RESET_TOKEN');
        if (empty($configuredToken)) {
            return new Response('Admin reset token not configured. Set ADMIN_RESET_TOKEN in environment.', Response::HTTP_FORBIDDEN);
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('token');
            $password = (string) $request->request->get('password', '');

            if (!hash_equals($configuredToken, (string) $token)) {
                $this->addFlash('error', 'Invalid token.');
                return $this->redirectToRoute('admin_reset_password');
            }

            if (strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters.');
                return $this->redirectToRoute('admin_reset_password');
            }

            $adminEmail = $this->params->get('ADMIN_EMAIL') ?: 'admin@vapeshop.ph';
            $user = $this->userRepository->findOneBy(['email' => $adminEmail]);

            if (!$user) {
                $this->addFlash('error', 'Admin user not found.');
                return $this->redirectToRoute('admin_reset_password');
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Admin password updated. Remove ADMIN_RESET_TOKEN from your environment now.');
            return $this->render('admin/reset_password_done.html.twig');
        }

        // GET: show the form
        return $this->render('admin/reset_password.html.twig', [
            'tokenPrefill' => $request->query->get('token', ''),
        ]);
    }
}

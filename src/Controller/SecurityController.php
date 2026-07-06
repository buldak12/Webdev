<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private CartService $cartService
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            /** @var User $user */
            $user = $this->getUser();
            if (in_array(User::ROLE_ADMIN, $user->getRoles())) {
                return $this->redirectToRoute('admin_dashboard');
            } elseif (in_array(User::ROLE_STAFF, $user->getRoles())) {
                return $this->redirectToRoute('staff_dashboard');
            }
            return $this->redirectToRoute('account_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'categories_menu' => $this->categoryRepository->findActive(),
            'cart_count' => 0,
        ]);
    }

    #[Route('/staff/login', name: 'staff_login')]
    public function staffLogin(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            /** @var User $user */
            $user = $this->getUser();
            if (in_array(User::ROLE_ADMIN, $user->getRoles(), true) || in_array(User::ROLE_STAFF, $user->getRoles(), true)) {
                return $this->redirectToRoute('staff_dashboard');
            }
            return $this->redirectToRoute('account_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/staff_login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/setup', name: 'app_setup')]
    public function setup(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if admin already exists
        $existingAdmin = $userRepository->findOneBy(['email' => 'admin@vapeshop.ph']);
        if ($existingAdmin) {
            $this->addFlash('info', 'Setup already completed. Admin account exists.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email', 'admin@vapeshop.ph');
            $password = $request->request->get('password');
            
            if (empty($password) || strlen($password) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters.');
                return $this->render('security/setup.html.twig');
            }

            // Create admin user
            $admin = new User();
            $admin->setEmail($email);
            $admin->setFirstName('Admin');
            $admin->setLastName('User');
            $admin->setRoles([User::ROLE_ADMIN]);
            $admin->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $admin->setIsEmailVerified(true);
            $admin->setPassword($passwordHasher->hashPassword($admin, $password));

            $entityManager->persist($admin);

            // Create a staff user for testing
            $staff = new User();
            $staff->setEmail('staff@vapeshop.ph');
            $staff->setFirstName('Staff');
            $staff->setLastName('User');
            $staff->setRoles([User::ROLE_STAFF]);
            $staff->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $staff->setIsEmailVerified(true);
            $staff->setPassword($passwordHasher->hashPassword($staff, $password));

            $entityManager->persist($staff);

            $user = new User();
            $user->setEmail('user@vapeshop.ph');
            $user->setFirstName('Regular');
            $user->setLastName('User');
            $user->setRoles([User::ROLE_CUSTOMER]);
            $user->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $user->setIsEmailVerified(true);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Admin, staff, and user accounts created successfully!');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/setup.html.twig');
    }
}

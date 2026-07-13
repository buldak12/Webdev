<?php

namespace App\Controller\Admin;

use App\Entity\AgeVerification;
use App\Entity\User;
use App\Repository\AgeVerificationRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class CustomerController extends AbstractController
{
    private function resolveRoute(Request $request, string $adminRoute, string $staffRoute): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');

        return str_starts_with($currentRoute, 'staff_customers') ? $staffRoute : $adminRoute;
    }

    private function redirectAfterWrite(Request $request, string $adminRoute, string $staffRoute, array $params = []): Response
    {
        return $this->redirectToRoute($this->resolveRoute($request, $adminRoute, $staffRoute), $params);
    }

    #[Route('/admin/customers', name: 'admin_customers')]
    #[Route('/staff/customers', name: 'staff_customers')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $status = $request->query->get('status');
        
        if ($status === 'pending') {
            $customers = $userRepository->findPendingAgeVerification();
        } else {
            $customers = $userRepository->findByRole(User::ROLE_CUSTOMER);
        }

        return $this->render('admin/customers/index.html.twig', [
            'customers' => $customers,
            'current_status' => $status,
            'customers_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_customers') ? 'staff_customers' : 'admin_customers',
        ]);
    }

    #[Route('/admin/customers/new', name: 'admin_customers_new')]
    #[Route('/staff/customers/new', name: 'staff_customers_new')]
    public function new(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');
            $phone = $request->request->get('phone');
            $password = $request->request->get('password');
            $ageVerified = $request->request->getBoolean('age_verified', false);

            // Validate
            if (!$email || !$firstName || !$lastName || !$password) {
                $this->addFlash('error', 'Email, first name, last name, and password are required.');
                return $this->redirectAfterWrite($request, 'admin_customers_new', 'staff_customers_new');
            }

            // Check if email exists
            if ($userRepository->findOneBy(['email' => $email])) {
                $this->addFlash('error', 'A customer with this email already exists.');
                return $this->redirectAfterWrite($request, 'admin_customers_new', 'staff_customers_new');
            }

            // Create user
            $customer = new User();
            $customer->setEmail($email);
            $customer->setFirstName($firstName);
            $customer->setLastName($lastName);
            $customer->setPhone($phone);
            $customer->setRoles([User::ROLE_CUSTOMER]);
            $customer->setIsActive(true);
            $customer->setIsEmailVerified(true);
            $customer->setPassword($passwordHasher->hashPassword($customer, $password));

            if ($ageVerified) {
                $customer->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
                $customer->setAgeVerifiedAt(new \DateTime());
            } else {
                $customer->setAgeVerificationStatus(User::AGE_STATUS_PENDING);
            }

            $em->persist($customer);
            $em->flush();

            $this->addFlash('success', sprintf('Customer %s %s created successfully.', $firstName, $lastName));
            return $this->redirectAfterWrite($request, 'admin_customers_show', 'staff_customers_show', ['id' => $customer->getId()]);
        }

        return $this->render('admin/customers/new.html.twig', [
            'customers_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_customers') ? 'staff_customers' : 'admin_customers',
        ]);
    }

    #[Route('/admin/customers/{id}', name: 'admin_customers_show', requirements: ['id' => '\d+'])]
    #[Route('/staff/customers/{id}', name: 'staff_customers_show', requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request, UserRepository $userRepository, OrderRepository $orderRepository): Response
    {
        $customer = $userRepository->find($id);
        if (!$customer) {
            throw $this->createNotFoundException('Customer not found');
        }

        $orders = $orderRepository->findByUser($customer, 10);
        $totalSpent = '0.00';
        foreach ($orders as $order) {
            if ($order->isPaid()) {
                $totalSpent = bcadd($totalSpent, $order->getTotal(), 2);
            }
        }

        return $this->render('admin/customers/show.html.twig', [
            'customer' => $customer,
            'orders' => $orders,
            'total_spent' => $totalSpent,
            'customers_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_customers') ? 'staff_customers' : 'admin_customers',
        ]);
    }

    #[Route('/admin/customers/{id}/verify', name: 'admin_customers_verify', methods: ['POST'])]
    #[Route('/staff/customers/{id}/verify', name: 'staff_customers_verify', methods: ['POST'])]
    public function verify(
        int $id,
        Request $request,
        UserRepository $userRepository,
        AgeVerificationRepository $ageVerificationRepository,
        EntityManagerInterface $em
    ): Response {
        $customer = $userRepository->find($id);
        if (!$customer) {
            throw $this->createNotFoundException('Customer not found');
        }

        $action = $request->request->get('action');
        
        // Find the latest pending verification
        $verification = $ageVerificationRepository->findOneBy(
            ['user' => $customer, 'status' => AgeVerification::STATUS_PENDING],
            ['createdAt' => 'DESC']
        );

        if ($action === 'approve') {
            $customer->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $customer->setAgeVerifiedAt(new \DateTime());
            
            if ($verification) {
                $verification->approve($this->getUser());
            }
            
            $this->addFlash('success', 'Customer age verified');
        } elseif ($action === 'reject') {
            $reason = $request->request->get('reason', 'Verification rejected');
            $customer->setAgeVerificationStatus(User::AGE_STATUS_REJECTED);
            
            if ($verification) {
                $verification->reject($this->getUser(), $reason);
            }
            
            $this->addFlash('warning', 'Customer verification rejected');
        }

        $em->flush();

        return $this->redirectAfterWrite($request, 'admin_customers', 'staff_customers');
    }

    #[Route('/admin/customers/{id}/points', name: 'admin_customers_points', methods: ['POST'])]
    #[Route('/staff/customers/{id}/points', name: 'staff_customers_points', methods: ['POST'])]
    public function adjustPoints(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $customer = $userRepository->find($id);
        if (!$customer) {
            throw $this->createNotFoundException('Customer not found');
        }

        $adjustment = (int) $request->request->get('points', 0);
        $newTotal = max(0, $customer->getLoyaltyPoints() + $adjustment);
        $customer->setLoyaltyPoints($newTotal);

        $em->flush();

        $this->addFlash('success', 'Loyalty points updated');
        return $this->redirectAfterWrite($request, 'admin_customers_show', 'staff_customers_show', ['id' => $id]);
    }

    #[Route('/admin/customers/{id}/toggle', name: 'admin_customers_toggle', methods: ['POST'])]
    #[Route('/staff/customers/{id}/toggle', name: 'staff_customers_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $customer = $userRepository->find($id);
        if ($customer) {
            $customer->setIsActive(!$customer->isActive());
            $em->flush();
            $this->addFlash('success', $customer->isActive() ? 'Customer activated' : 'Customer deactivated');
        }

        return $this->redirectAfterWrite($request, 'admin_customers', 'staff_customers');
    }
}

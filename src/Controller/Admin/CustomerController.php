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
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class CustomerController extends AbstractController
{
    #[Route('/customers', name: 'admin_customers')]
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
        ]);
    }

    #[Route('/customers/{id}', name: 'admin_customers_show', requirements: ['id' => '\d+'])]
    public function show(int $id, UserRepository $userRepository, OrderRepository $orderRepository): Response
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
        ]);
    }

    #[Route('/customers/{id}/verify', name: 'admin_customers_verify', methods: ['POST'])]
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

        return $this->redirectToRoute('admin_customers');
    }

    #[Route('/customers/{id}/points', name: 'admin_customers_points', methods: ['POST'])]
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
        return $this->redirectToRoute('admin_customers_show', ['id' => $id]);
    }

    #[Route('/customers/{id}/toggle', name: 'admin_customers_toggle', methods: ['POST'])]
    public function toggle(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $customer = $userRepository->find($id);
        if ($customer) {
            $customer->setIsActive(!$customer->isActive());
            $em->flush();
            $this->addFlash('success', $customer->isActive() ? 'Customer activated' : 'Customer deactivated');
        }

        return $this->redirectToRoute('admin_customers');
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class UserController extends AbstractController
{
    #[Route('/users', name: 'admin_users')]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $role = (string) $request->query->get('role', 'all');
        $status = (string) $request->query->get('status', 'all');

        $qb = $userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if ($search !== '') {
            $qb
                ->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $roleFilters = [
            'admin' => User::ROLE_ADMIN,
            'staff' => User::ROLE_STAFF,
            'customer' => User::ROLE_CUSTOMER,
        ];

        if (isset($roleFilters[$role])) {
            $qb
                ->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"' . $roleFilters[$role] . '"%');
        }

        if ($status === 'active') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', false);
        }

        return $this->render('admin/users/index.html.twig', [
            'users' => $qb->getQuery()->getResult(),
            'current_query' => $search,
            'current_role' => $role,
            'current_status' => $status,
        ]);
    }

    #[Route('/users/{id}/role', name: 'admin_users_role', methods: ['POST'])]
    public function updateRole(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$this->isCsrfTokenValid('user-role-' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_users');
        }

        $newRole = (string) $request->request->get('role', '');
        $allowedRoles = [User::ROLE_ADMIN, User::ROLE_STAFF, User::ROLE_CUSTOMER];

        if (!in_array($newRole, $allowedRoles, true)) {
            $this->addFlash('error', 'Invalid role selected.');
            return $this->redirectToRoute('admin_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId() && $newRole !== User::ROLE_ADMIN) {
            $this->addFlash('error', 'You cannot remove your own admin role.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setRoles([$newRole]);
        $em->flush();

        $this->addFlash('success', 'User role updated successfully.');

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/toggle', name: 'admin_users_toggle', methods: ['POST'])]
    public function toggleStatus(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepository->find($id);
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        if (!$this->isCsrfTokenValid('user-toggle-' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_users');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'You cannot deactivate your own account.');
            return $this->redirectToRoute('admin_users');
        }

        $user->setIsActive(!$user->isActive());
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'User activated.' : 'User deactivated.');

        return $this->redirectToRoute('admin_users');
    }
}

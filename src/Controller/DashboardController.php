<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return $this->redirectToRoute('admin_dashboard');
        }

        if (in_array(User::ROLE_STAFF, $user->getRoles(), true)) {
            return $this->redirectToRoute('staff_dashboard');
        }

        return $this->redirectToRoute('account_index');
    }
}
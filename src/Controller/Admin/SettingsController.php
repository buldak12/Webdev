<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'admin_settings')]
    public function index(): Response
    {
        return $this->render('admin/settings/index.html.twig');
    }
}

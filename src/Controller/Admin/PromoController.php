<?php

namespace App\Controller\Admin;

use App\Entity\PromoCode;
use App\Repository\PromoCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PromoController extends AbstractController
{
    private function resolveRoute(Request $request, string $adminRoute, string $staffRoute): string
    {
        $currentRoute = (string) $request->attributes->get('_route', '');

        return str_starts_with($currentRoute, 'staff_promos') ? $staffRoute : $adminRoute;
    }

    private function redirectAfterWrite(Request $request, string $adminRoute, string $staffRoute, array $params = []): Response
    {
        return $this->redirectToRoute($this->resolveRoute($request, $adminRoute, $staffRoute), $params);
    }

    #[Route('/admin/promos', name: 'admin_promos')]
    #[Route('/staff/promos', name: 'staff_promos')]
    public function index(PromoCodeRepository $promoCodeRepository, Request $request): Response
    {
        $filter = $request->query->get('filter');
        
        if ($filter === 'active') {
            $promos = $promoCodeRepository->findActive();
        } elseif ($filter === 'expired') {
            $promos = $promoCodeRepository->findExpired();
        } else {
            $promos = $promoCodeRepository->findBy([], ['createdAt' => 'DESC']);
        }

        return $this->render('admin/promos/index.html.twig', [
            'promos' => $promos,
            'current_filter' => $filter,
            'types' => PromoCode::TYPES,
            'promos_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_promos') ? 'staff_promos' : 'admin_promos',
        ]);
    }

    #[Route('/admin/promos/new', name: 'admin_promos_new', methods: ['GET', 'POST'])]
    #[Route('/staff/promos/new', name: 'staff_promos_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $promo = new PromoCode();
            $promo->setCode($request->request->get('code'));
            $promo->setDescription($request->request->get('description'));
            $promo->setType($request->request->get('type'));
            $promo->setValue($request->request->get('value'));
            
            if ($minOrder = $request->request->get('minimum_order_amount')) {
                $promo->setMinimumOrderAmount($minOrder);
            }
            if ($maxDiscount = $request->request->get('maximum_discount')) {
                $promo->setMaximumDiscount($maxDiscount);
            }
            if ($usageLimit = $request->request->get('usage_limit')) {
                $promo->setUsageLimit((int) $usageLimit);
            }
            if ($perUserLimit = $request->request->get('usage_limit_per_user')) {
                $promo->setUsageLimitPerUser((int) $perUserLimit);
            }
            if ($startsAt = $request->request->get('starts_at')) {
                $promo->setStartsAt(new \DateTime($startsAt));
            }
            if ($expiresAt = $request->request->get('expires_at')) {
                $promo->setExpiresAt(new \DateTime($expiresAt));
            }
            
            $promo->setIsActive($request->request->getBoolean('is_active', true));

            $em->persist($promo);
            $em->flush();

            $this->addFlash('success', 'Promo code created successfully');
            return $this->redirectAfterWrite($request, 'admin_promos', 'staff_promos');
        }

        return $this->render('admin/promos/form.html.twig', [
            'promo' => null,
            'types' => PromoCode::TYPES,
            'promos_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_promos') ? 'staff_promos' : 'admin_promos',
        ]);
    }

    #[Route('/admin/promos/{id}/edit', name: 'admin_promos_edit', methods: ['GET', 'POST'])]
    #[Route('/staff/promos/{id}/edit', name: 'staff_promos_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, PromoCodeRepository $promoCodeRepository, EntityManagerInterface $em): Response
    {
        $promo = $promoCodeRepository->find($id);
        if (!$promo) {
            throw $this->createNotFoundException('Promo code not found');
        }

        if ($request->isMethod('POST')) {
            $promo->setDescription($request->request->get('description'));
            $promo->setType($request->request->get('type'));
            $promo->setValue($request->request->get('value'));
            
            $promo->setMinimumOrderAmount($request->request->get('minimum_order_amount') ?: null);
            $promo->setMaximumDiscount($request->request->get('maximum_discount') ?: null);
            $promo->setUsageLimit($request->request->get('usage_limit') ? (int) $request->request->get('usage_limit') : null);
            $promo->setUsageLimitPerUser($request->request->get('usage_limit_per_user') ? (int) $request->request->get('usage_limit_per_user') : null);
            
            if ($startsAt = $request->request->get('starts_at')) {
                $promo->setStartsAt(new \DateTime($startsAt));
            } else {
                $promo->setStartsAt(null);
            }
            if ($expiresAt = $request->request->get('expires_at')) {
                $promo->setExpiresAt(new \DateTime($expiresAt));
            } else {
                $promo->setExpiresAt(null);
            }
            
            $promo->setIsActive($request->request->getBoolean('is_active', true));

            $em->flush();

            $this->addFlash('success', 'Promo code updated');
            return $this->redirectAfterWrite($request, 'admin_promos', 'staff_promos');
        }

        return $this->render('admin/promos/form.html.twig', [
            'promo' => $promo,
            'types' => PromoCode::TYPES,
            'promos_route_prefix' => str_starts_with((string) $request->attributes->get('_route', ''), 'staff_promos') ? 'staff_promos' : 'admin_promos',
        ]);
    }

    #[Route('/admin/promos/{id}/toggle', name: 'admin_promos_toggle', methods: ['POST'])]
    #[Route('/staff/promos/{id}/toggle', name: 'staff_promos_toggle', methods: ['POST'])]
    public function toggle(int $id, Request $request, PromoCodeRepository $promoCodeRepository, EntityManagerInterface $em): Response
    {
        $promo = $promoCodeRepository->find($id);
        if ($promo) {
            $promo->setIsActive(!$promo->isActive());
            $em->flush();
            $this->addFlash('success', $promo->isActive() ? 'Promo code activated' : 'Promo code deactivated');
        }

        return $this->redirectAfterWrite($request, 'admin_promos', 'staff_promos');
    }

    #[Route('/admin/promos/{id}/delete', name: 'admin_promos_delete', methods: ['POST'])]
    #[Route('/staff/promos/{id}/delete', name: 'staff_promos_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, PromoCodeRepository $promoCodeRepository, EntityManagerInterface $em): Response
    {
        $promo = $promoCodeRepository->find($id);
        if ($promo) {
            $em->remove($promo);
            $em->flush();
            $this->addFlash('success', 'Promo code deleted');
        }

        return $this->redirectAfterWrite($request, 'admin_promos', 'staff_promos');
    }
}

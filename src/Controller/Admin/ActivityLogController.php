<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class ActivityLogController extends AbstractController
{
    #[Route('/activity-logs', name: 'admin_activity_logs')]
    public function index(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $method = strtoupper((string) $request->query->get('method', ''));

        $qb = $activityLogRepository->createQueryBuilder('l')
            ->leftJoin('l.actor', 'a')
            ->addSelect('a')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(200);

        if ($query !== '') {
            $qb
                ->andWhere('l.action LIKE :q OR l.routeName LIKE :q OR a.email LIKE :q')
                ->setParameter('q', '%' . $query . '%');
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $qb
                ->andWhere('l.method = :method')
                ->setParameter('method', $method);
        }

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $qb->getQuery()->getResult(),
            'current_query' => $query,
            'current_method' => $method,
        ]);
    }
}

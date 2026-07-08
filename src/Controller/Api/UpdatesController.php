<?php

namespace App\Controller\Api;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class UpdatesController extends AbstractController
{
    /**
     * Get updates since timestamp - Powers real-time updates in mobile app
     * GET /api/updates?since=1234567890&types=products,orders
     */
    #[Route('/updates', name: 'api_updates', methods: ['GET'])]
    public function getUpdates(
        Request $request,
        ProductRepository $productRepository,
        OrderRepository $orderRepository
    ): JsonResponse {
        $since = $request->query->getInt('since', 0);
        $types = explode(',', $request->query->getString('types', 'products'));
        
        $sinceDate = new \DateTime();
        $sinceDate->setTimestamp($since);

        $updates = [];

        // Get product updates
        if (in_array('products', $types)) {
            $qb = $productRepository->createQueryBuilder('p')
                ->where('p.updatedAt > :since')
                ->andWhere('p.isActive = true')
                ->setParameter('since', $sinceDate)
                ->orderBy('p.updatedAt', 'DESC')
                ->setMaxResults(50);

            $updatedProducts = $qb->getQuery()->getResult();

            if (!empty($updatedProducts)) {
                $updates['products'] = array_map(function($product) {
                    return [
                        'id' => $product->getId(),
                        'name' => $product->getName(),
                        'base_price' => $product->getBasePrice(),
                        'available_stock' => $product->getAvailableStock(),
                        'updated_at' => $product->getUpdatedAt()?->getTimestamp(),
                    ];
                }, $updatedProducts);
            }
        }

        // Get order updates (for authenticated user)
        if (in_array('orders', $types) && $this->getUser()) {
            $userOrders = $orderRepository->createQueryBuilder('o')
                ->where('o.user = :user')
                ->andWhere('o.updatedAt > :since')
                ->setParameter('user', $this->getUser())
                ->setParameter('since', $sinceDate)
                ->orderBy('o.updatedAt', 'DESC')
                ->setMaxResults(20)
                ->getQuery()
                ->getResult();

            if (!empty($userOrders)) {
                $updates['orders'] = array_map(function($order) {
                    return [
                        'id' => $order->getId(),
                        'order_number' => $order->getOrderNumber(),
                        'status' => $order->getStatus(),
                        'total' => $order->getTotal(),
                        'updated_at' => $order->getUpdatedAt()?->getTimestamp(),
                    ];
                }, $userOrders);
            }
        }

        return $this->json([
            'updates' => $updates,
            'timestamp' => time(),
            'has_updates' => !empty($updates),
        ]);
    }

    /**
     * Get stock status for multiple variants
     * GET /api/stock/check?variants=1,2,3
     */
    #[Route('/stock/check', name: 'api_stock_check', methods: ['GET'])]
    public function checkStock(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $variantIds = array_filter(array_map('intval', explode(',', $request->query->getString('variants'))));

        if (empty($variantIds)) {
            return $this->json(['stock' => []]);
        }

        $variants = $em->createQueryBuilder()
            ->select('v.id, v.stock, v.reservedStock')
            ->from('App\Entity\ProductVariant', 'v')
            ->where('v.id IN (:ids)')
            ->andWhere('v.isActive = true')
            ->setParameter('ids', $variantIds)
            ->getQuery()
            ->getResult();

        $stock = [];
        foreach ($variants as $variant) {
            $stock[$variant['id']] = [
                'available' => max(0, $variant['stock'] - $variant['reservedStock']),
                'in_stock' => ($variant['stock'] - $variant['reservedStock']) > 0,
            ];
        }

        return $this->json(['stock' => $stock]);
    }
}

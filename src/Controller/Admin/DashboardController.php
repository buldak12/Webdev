<?php

namespace App\Controller\Admin;

use App\Repository\AgeVerificationRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\UserRepository;
use App\Service\InventoryService;
use App\Service\OrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard')]
    public function index(
        OrderRepository $orderRepository,
        UserRepository $userRepository,
        ProductVariantRepository $variantRepository,
        AgeVerificationRepository $ageVerificationRepository,
        OrderService $orderService,
        InventoryService $inventoryService,
        ProductRepository $productRepository
    ): Response {
        // Get date ranges
        $today = new \DateTime('today');
        $thisMonth = new \DateTime('first day of this month');
        
        // Sales metrics
        $todayRevenue = $orderRepository->getTotalRevenue($today);
        $monthRevenue = $orderRepository->getTotalRevenue($thisMonth);
        
        // Order summary
        $orderSummary = $orderService->getOrderSummary();
        
        // Recent orders
        $recentOrders = $orderRepository->findRecentOrders(5);
        
        // Low stock alerts
        $lowStockVariants = $variantRepository->findLowStock();

        // Inventory overview
        $inventorySummary = $inventoryService->getStockSummary();
        $productCount = $productRepository->count([]);
        
        // Pending age verifications
        $pendingVerifications = $ageVerificationRepository->findPending();
        
        // Regional sales data
        $regionalSales = $orderRepository->getOrdersByRegion($thisMonth);
        
        // Customer count
        $customerCount = count($userRepository->findByRole('ROLE_CUSTOMER'));

        return $this->render('admin/dashboard/index.html.twig', [
            'today_revenue' => $todayRevenue,
            'month_revenue' => $monthRevenue,
            'order_summary' => $orderSummary,
            'recent_orders' => $recentOrders,
            'low_stock_variants' => array_slice($lowStockVariants, 0, 5),
            'pending_verifications' => array_slice($pendingVerifications, 0, 5),
            'regional_sales' => $regionalSales,
            'customer_count' => $customerCount,
            'inventory_summary' => $inventorySummary,
            'product_count' => $productCount,
        ]);
    }
}
